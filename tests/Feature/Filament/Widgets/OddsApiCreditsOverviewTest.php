<?php

use App\Enums\AdminRole;
use App\Filament\Widgets\OddsApiCreditsOverview;
use App\Filament\Widgets\RecentOddsApiCalls;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Filament\Facades\Filament;

test('shows the Odds API credit widgets only to admins who manage odds or competitions', function (AdminRole $role, bool $visible) {
    $this->seed(AdminRoleSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->withRole($role)->create(), 'admin');

    expect(OddsApiCreditsOverview::canView())->toBe($visible)
        ->and(RecentOddsApiCalls::canView())->toBe($visible);
})->with([
    'super admin' => [AdminRole::SuperAdmin, true],
    'manager' => [AdminRole::Manager, true],
    'trader' => [AdminRole::Trader, true],
    'finance' => [AdminRole::Finance, false],
    'support' => [AdminRole::Support, false],
]);
