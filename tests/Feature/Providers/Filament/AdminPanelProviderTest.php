<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;

test('guests are redirected to the admin sign-in page', function () {
    $response = $this->get(route('filament.admin.pages.dashboard'));

    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('a signed-in player is treated as a guest by the admin panel', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(route('filament.admin.pages.dashboard'));

    $response->assertRedirect(route('filament.admin.auth.login'));
});

test('admins who are not allowed into the panel are forbidden', function (Admin $admin) {
    $this->actingAs($admin, 'admin');

    $response = $this->get(route('filament.admin.pages.dashboard'));

    $response->assertForbidden();
})->with([
    'deactivated admin' => fn () => Admin::factory()->inactive()->withAppAuthentication()->withRole(AdminRole::Manager)->create(),
    'admin without a role' => fn () => Admin::factory()->withAppAuthentication()->create(),
]);

test('admins without an authenticator app are sent to set one up before anything else', function () {
    $admin = Admin::factory()->withRole(AdminRole::Manager)->create();
    $this->actingAs($admin, 'admin');

    $response = $this->get(route('filament.admin.pages.dashboard'));

    $response->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));
});

test('an active admin with a role and an authenticator app can open the dashboard', function () {
    $admin = Admin::factory()->withAppAuthentication()->withRole(AdminRole::Support)->create();
    $this->actingAs($admin, 'admin');

    $response = $this->get(route('filament.admin.pages.dashboard'));

    $response->assertOk();
});

test('the admin panel does not answer on the player domain', function () {
    $response = $this->get('/admins');

    $response->assertNotFound();
});
