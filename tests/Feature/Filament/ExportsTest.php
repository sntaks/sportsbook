<?php

use App\Enums\AdminRole;
use App\Enums\BetStatus;
use App\Enums\MpesaDepositStatus;
use App\Enums\WalletTransactionType;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Bets\Pages\ListBets;
use App\Filament\Resources\Deposits\Pages\ManageDeposits;
use App\Models\Admin;
use App\Models\MpesaDeposit;
use App\Models\User;
use App\Reports\Period;
use App\Reports\TaxReportFile;
use App\Wallet\Ledger;
use Database\Seeders\AdminRoleSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
    Storage::fake('local');
    // As in production: the export runs on a worker and its "Export ready" notification is stored for the admin.
    config(['queue.default' => 'database']);
    $this->travelTo(now()->timezone('Africa/Nairobi')->setDate(2026, 10, 15)->setTime(12, 0));
});

/**
 * Run the queued export, then return the download link from the admin's "Export ready" notification.
 */
function exportDownloadUrl(Admin $admin, string $format = 'csv'): string
{
    Artisan::call('queue:work', ['--stop-when-empty' => true, '--tries' => 1]);

    return collect($admin->notifications()->latest()->firstOrFail()->data['actions'])->firstWhere('name', "download_{$format}")['url'];
}

/**
 * The export's file as the admin downloads it from the notification.
 */
function downloadedExport(Admin $admin, string $format = 'csv'): string
{
    // The worker resets the auth guards, so it runs before the admin is signed in for the download.
    $url = exportDownloadUrl($admin, $format);

    return test()->actingAs($admin, 'admin')->get($url)->assertOk()->streamedContent();
}

test('finance exports the filtered bets list as CSV and Excel, with EAT times and plain amounts, and the export is logged', function () {
    $finance = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->actingAs($finance, 'admin');
    $open = betOn(bettor(), [[pricedFixture(), 'home']], '150');
    $cancelled = betOn(bettor(), [[pricedFixture(), 'home']]);
    $cancelled->update(['status' => BetStatus::Cancelled]);

    Livewire::test(ListBets::class)
        ->filterTable('status', BetStatus::Open->value)
        ->callAction('export')
        ->assertHasNoActionErrors();

    $export = Export::query()->sole();
    $csv = downloadedExport($finance);

    expect($export->user->is($finance))->toBeTrue()
        ->and($csv)->toContain('Reference,"Placed (EAT)","Player phone"')
        ->and($csv)->toContain("{$open->reference},\"2026-10-15 12:00:00\",{$open->user->phone},Single,Open,150.00,No,2.10,1,315.00")
        ->and($csv)->not->toContain($cancelled->reference)
        ->and(Storage::disk('local')->exists("filament_exports/{$export->id}/{$export->file_name}.xlsx"))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'exports')->sole())
        ->causer->is($finance)->toBeTrue()
        ->properties->toArray()->toBe(['exporter' => 'BetExporter', 'rows' => 1, 'filters' => ['status.value' => 'open']]);
});

test('text that a spreadsheet would run as a formula is written as plain text', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');
    MpesaDeposit::factory()->create(['status' => MpesaDepositStatus::Dismissed, 'resolution_note' => '=HYPERLINK("https://evil.test")']);

    Livewire::test(ManageDeposits::class)->callAction('export');

    expect(downloadedExport($manager))->toContain('"\'=HYPERLINK(""https://evil.test"")"');
});

test('the ledger export covers only the days chosen on the Reports page', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');
    $player = User::factory()->create();
    $ledger = app(Ledger::class);
    $ledger->post($player, WalletTransactionType::Deposit, 10_000, 'test:inside', description: 'Inside the range');
    $this->travel(-3)->days();
    $ledger->post($player, WalletTransactionType::Deposit, 20_000, 'test:outside', description: 'Before the range');
    $this->travel(3)->days();

    Livewire::test(Reports::class)
        ->set('filters', ['from' => '2026-10-14', 'until' => '2026-10-15'])
        ->callAction('export');

    expect(downloadedExport($manager))
        ->toContain('Inside the range')
        ->toContain('Deposit,100.00,100.00')
        ->not->toContain('Before the range');
});

test('only the admin who ran an export can download it, and only while they may still export', function () {
    $owner = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->actingAs($owner, 'admin');
    betOn(bettor(), [[pricedFixture(), 'home']]);
    Livewire::test(ListBets::class)->callAction('export');
    $url = exportDownloadUrl($owner);

    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin')->get($url)->assertForbidden();
    $this->actingAs($owner, 'admin')->get($url)->assertOk();

    $owner->syncRoles([AdminRole::Support->value]);

    $this->actingAs($owner->fresh(), 'admin')->get($url)->assertForbidden();
});

test('staff who may not export data see no export or tax download buttons', function (AdminRole $role) {
    $this->actingAs(Admin::factory()->withRole($role)->create(), 'admin');

    Livewire::test(ListBets::class)->assertActionHidden('export');
})->with([AdminRole::Support, AdminRole::Trader]);

test('the tax report downloads straight away as CSV or Excel and is logged', function () {
    $finance = Admin::factory()->withRole(AdminRole::Finance)->create();
    $this->actingAs($finance, 'admin');
    $player = User::factory()->create();
    app(Ledger::class)->post($player, WalletTransactionType::Deposit, 10_000, 'test:deposit');
    app(Ledger::class)->post($player, WalletTransactionType::DepositTax, -500, 'test:tax');

    Livewire::test(Reports::class)
        ->set('filters', ['from' => '2026-10-15', 'until' => '2026-10-15'])
        ->callAction('downloadTaxReportCsv')
        ->assertFileDownloaded('tax-report-2026-10-15-to-2026-10-15.csv')
        ->callAction('downloadTaxReportXlsx')
        ->assertFileDownloaded('tax-report-2026-10-15-to-2026-10-15.xlsx');

    expect(app(TaxReportFile::class)->rows(Period::between('2026-10-15', '2026-10-15')))->toBe([
        ['Kind', 'Period (EAT)', 'Tax on deposits (KES)', 'Tax on winnings (KES)', 'Total (KES)'],
        ['Day', '2026-10-15', '5.00', '0.00', '5.00'],
        ['Month', '2026-10', '5.00', '0.00', '5.00'],
    ])->and(Activity::query()->where('log_name', 'exports')->where('event', 'export')->count())->toBe(2);
});

test('exports older than the retention period are deleted with their files', function () {
    $admin = Admin::factory()->withRole(AdminRole::Manager)->create();
    $old = Export::query()->forceCreate(['file_disk' => 'local', 'file_name' => 'old', 'exporter' => 'x', 'total_rows' => 1, 'user_type' => $admin->getMorphClass(), 'user_id' => $admin->id, 'created_at' => now()->subDays(8)]);
    $recent = Export::query()->forceCreate(['file_disk' => 'local', 'file_name' => 'recent', 'exporter' => 'x', 'total_rows' => 1, 'user_type' => $admin->getMorphClass(), 'user_id' => $admin->id, 'created_at' => now()->subDays(6)]);
    Storage::disk('local')->put("filament_exports/{$old->id}/0000000000000001.csv", 'data');
    Storage::disk('local')->put("filament_exports/{$recent->id}/0000000000000001.csv", 'data');

    $this->artisan('exports:prune')->expectsOutput('Deleted 1 exports.')->assertSuccessful();

    expect(Export::query()->pluck('id')->all())->toBe([$recent->id])
        ->and(Storage::disk('local')->exists("filament_exports/{$old->id}"))->toBeFalse()
        ->and(Storage::disk('local')->exists("filament_exports/{$recent->id}/0000000000000001.csv"))->toBeTrue();
});
