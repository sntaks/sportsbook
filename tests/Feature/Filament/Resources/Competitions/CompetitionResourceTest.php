<?php

use App\Enums\AdminRole;
use App\Filament\Resources\Competitions\Pages\ManageCompetitions;
use App\Models\Admin;
use App\Models\Competition;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->seed(AdminRoleSeeder::class);
});

test('a manager switches syncing off and the change is logged', function () {
    $manager = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');
    $competition = Competition::factory()->synced()->create();

    Livewire::test(ManageCompetitions::class)->call('updateTableColumnState', 'is_synced', (string) $competition->id, false);

    expect($competition->fresh()->is_synced)->toBeFalse()
        ->and(Activity::query()->where('log_name', 'odds')->where('event', 'updated')->where('subject_id', $competition->id)->sole()->causer->is($manager))->toBeTrue();
});

test('support staff cannot switch syncing, even by calling the update directly', function () {
    $this->actingAs(Admin::factory()->withRole(AdminRole::Support)->create(), 'admin');
    $competition = Competition::factory()->synced()->create();

    Livewire::test(ManageCompetitions::class)->call('updateTableColumnState', 'is_synced', (string) $competition->id, false);

    expect($competition->fresh()->is_synced)->toBeTrue();
});
