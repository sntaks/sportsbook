<?php

use App\Enums\AdminRole;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Models\Admin;
use App\Models\Promotion;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
    Storage::fake('public');
});

test('a manager publishes a promotion with a banner, entered in Kenya time, and the change is logged', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');

    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'title' => 'Weekend Boost',
            'slug' => 'weekend-boost',
            'summary' => 'Bigger odds all weekend.',
            'body' => "## Terms\n\nSingles only.",
            'image_path' => UploadedFile::fake()->image('banner.jpg', 800, 400),
            'starts_at' => '2026-10-24 09:00',
            'ends_at' => '2026-10-26 23:00',
            'is_published' => true,
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $promotion = Promotion::query()->sole();
    expect($promotion)
        ->slug->toBe('weekend-boost')
        ->is_published->toBeTrue()
        ->and($promotion->starts_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-24 06:00')
        ->and($promotion->image_path)->toStartWith('promotions/')
        ->and(Storage::disk('public')->exists($promotion->image_path))->toBeTrue()
        ->and(Activity::query()->where('log_name', 'promotions')->sole()->causer->is($manager))->toBeTrue();
});

test('a new promotion\'s link follows its title', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');

    Livewire::test(CreatePromotion::class)
        ->fillForm(['title' => 'Cash Out Week'])
        ->assertSchemaStateSet(['slug' => 'cash-out-week']);
});

test('a promotion needs an image file, a unique link and an end after its start', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    Promotion::factory()->create(['slug' => 'taken']);

    Livewire::test(CreatePromotion::class)
        ->fillForm([
            'title' => 'Bad Promotion',
            'slug' => 'taken',
            'summary' => 'Nope.',
            'body' => 'Text.',
            'image_path' => UploadedFile::fake()->create('terms.pdf', 100, 'application/pdf'),
            'starts_at' => '2026-10-24 09:00',
            'ends_at' => '2026-10-23 09:00',
        ])
        ->call('create')
        ->assertHasFormErrors(['slug', 'image_path', 'ends_at']);

    expect(Promotion::query()->count())->toBe(1);
});

test('the list shows each promotion\'s status, and deleting one removes its image', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Manager)->create(), 'admin');
    Storage::disk('public')->put('promotions/banner.jpg', 'image');
    $live = Promotion::factory()->create(['image_path' => 'promotions/banner.jpg']);
    $draft = Promotion::factory()->draft()->create();

    Livewire::test(ListPromotions::class)
        ->assertCanSeeTableRecords([$live, $draft])
        ->assertTableColumnStateSet('state', 'Live', $live)
        ->assertTableColumnStateSet('state', 'Draft', $draft);

    Livewire::test(EditPromotion::class, ['record' => $live->getRouteKey()])->callAction('delete');

    expect(Promotion::query()->whereKey($live->id)->exists())->toBeFalse()
        ->and(Storage::disk('public')->exists('promotions/banner.jpg'))->toBeFalse();
});

test('staff other than managers cannot see promotions', function (AdminRole $role) {
    $this->actingAs(Admin::factory()->withAppAuthentication()->withRole($role)->create(), 'admin');

    $this->get(PromotionResource::getUrl('index'))->assertForbidden();
    $this->get(PromotionResource::getUrl('edit', ['record' => Promotion::factory()->create()]))->assertForbidden();
})->with([AdminRole::Support, AdminRole::Trader, AdminRole::Finance]);
