<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login as LoginEvent;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

test('records a failed admin sign-in with the attempted email but never the password', function () {
    Admin::factory()->withAppAuthentication()->withRole(AdminRole::Support)->create(['email' => 'support@example.com']);
    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'support@example.com', 'password' => 'not-the-password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $activity = Activity::query()->where('event', 'login-failed')->sole();
    expect($activity->properties->toArray())->toBe(['email' => 'support@example.com', 'ip' => '127.0.0.1'])
        ->and($activity->causer_id)->toBeNull();
});

test('records a failed sign-in by a deactivated admin whose password was correct', function () {
    Admin::factory()->inactive()->withAppAuthentication()->withRole(AdminRole::Support)->create(['email' => 'support@example.com']);
    Filament::setCurrentPanel('admin');

    Livewire::test(Login::class)
        ->fillForm(['email' => 'support@example.com', 'password' => 'password'])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    $this->assertGuest('admin');
    expect(Activity::query()->where('event', 'login-failed')->count())->toBe(1);
});

test('records a successful admin sign-in and stamps the last sign-in time', function () {
    $this->freezeSecond();
    $admin = Admin::factory()->create();

    event(new LoginEvent('admin', $admin, false));

    $activity = Activity::query()->where('event', 'login')->sole();
    expect($activity->causer->is($admin))->toBeTrue()
        ->and($activity->subject->is($admin))->toBeTrue()
        ->and($admin->fresh()->last_login_at->equalTo(now()))->toBeTrue();
});

test('does not record player sign-ins', function () {
    $user = User::factory()->create();

    event(new LoginEvent('web', $user, false));

    expect(Activity::query()->whereIn('event', ['login', 'login-failed'])->exists())->toBeFalse();
});
