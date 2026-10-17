<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

test('creates an admin with the chosen role and a hashed password', function () {
    $this->artisan('admin:create', ['--name' => 'Jane Wanjiru', '--email' => 'jane@example.com', '--role' => 'finance'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsOutputToContain('Admin jane@example.com created with the finance role.')
        ->assertSuccessful();

    $admin = Admin::firstWhere('email', 'jane@example.com');
    expect($admin->name)->toBe('Jane Wanjiru')
        ->and($admin->getRoleNames()->all())->toBe(['finance'])
        ->and(Hash::check('correct-horse-battery', $admin->password))->toBeTrue();
});

test('rejects an email that already belongs to an admin', function () {
    Admin::factory()->create(['email' => 'jane@example.com']);

    $this->artisan('admin:create', ['--name' => 'Jane Wanjiru', '--email' => 'jane@example.com', '--role' => 'finance'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsOutputToContain('The email has already been taken.')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 1);
});

test('rejects a role that does not exist', function () {
    $this->artisan('admin:create', ['--name' => 'Jane Wanjiru', '--email' => 'jane@example.com', '--role' => 'owner'])
        ->expectsQuestion('Password', 'correct-horse-battery')
        ->expectsOutputToContain('The selected role is invalid.')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 0);
});
