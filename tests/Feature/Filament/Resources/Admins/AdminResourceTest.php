<?php

use App\Enums\AdminRole;
use App\Filament\Resources\Admins\Pages\ManageAdmins;
use App\Models\Admin;
use Database\Seeders\AdminRoleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
});

test('a manager cannot open the admin accounts screen', function () {
    $this->seed(AdminRoleSeeder::class);
    $manager = Admin::factory()->withAppAuthentication()->withRole(AdminRole::Manager)->create();
    $this->actingAs($manager, 'admin');

    $response = $this->get(route('filament.admin.resources.admins.index'));

    $response->assertForbidden();
});

test('a super admin creates an admin with a role', function () {
    $this->seed(AdminRoleSeeder::class);
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    Livewire::test(ManageAdmins::class)
        ->callAction('create', data: [
            'name' => 'Brian Otieno',
            'email' => 'brian@example.com',
            'role' => AdminRole::Trader->value,
            'password' => 'correct-horse-battery',
            'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $admin = Admin::firstWhere('email', 'brian@example.com');
    expect($admin->getRoleNames()->all())->toBe(['trader'])
        ->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue();
});

test('a new admin requires a password', function () {
    $this->seed(AdminRoleSeeder::class);
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');

    Livewire::test(ManageAdmins::class)
        ->callAction('create', data: [
            'name' => 'Brian Otieno',
            'email' => 'brian@example.com',
            'role' => AdminRole::Trader->value,
            'password' => '',
        ])
        ->assertHasFormErrors(['password' => 'required']);

    $this->assertDatabaseMissing('admins', ['email' => 'brian@example.com']);
});

test('editing an admin changes their role and keeps their password when it is left blank', function () {
    $this->seed(AdminRoleSeeder::class);
    $this->actingAs(Admin::factory()->withRole(AdminRole::SuperAdmin)->create(), 'admin');
    $admin = Admin::factory()->withRole(AdminRole::Support)->create();

    Livewire::test(ManageAdmins::class)
        ->callAction(TestAction::make('edit')->table($admin), data: [
            'role' => AdminRole::Finance->value,
            'password' => '',
        ])
        ->assertHasNoFormErrors();

    $admin->refresh();
    expect($admin->getRoleNames()->all())->toBe(['finance'])
        ->and(Hash::check('password', $admin->password))->toBeTrue();
});

test('an admin cannot change their own role or deactivate themselves', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $this->actingAs($superAdmin, 'admin');

    Livewire::test(ManageAdmins::class)
        ->callAction(TestAction::make('edit')->table($superAdmin), data: [
            'role' => AdminRole::Support->value,
            'is_active' => false,
        ]);

    $superAdmin->refresh();
    expect($superAdmin->getRoleNames()->all())->toBe(['super-admin'])
        ->and($superAdmin->is_active)->toBeTrue();
});

test('resetting an admin\'s 2FA clears their authenticator and records who did it', function () {
    $this->seed(AdminRoleSeeder::class);
    $superAdmin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $this->actingAs($superAdmin, 'admin');
    $admin = Admin::factory()->withAppAuthentication()->withRole(AdminRole::Support)->create();

    Livewire::test(ManageAdmins::class)
        ->callAction(TestAction::make('resetAuthenticator')->table($admin));

    $activity = Activity::query()->where('event', 'authenticator-reset')->sole();
    expect($admin->fresh()->getAppAuthenticationSecret())->toBeNull()
        ->and($activity->causer->is($superAdmin))->toBeTrue()
        ->and($activity->subject->is($admin))->toBeTrue();
});
