<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Known accounts for trying the app locally. Every password is "password" and every admin except
 * newadmin@sportsbook.test shares one authenticator secret, so any TOTP app can sign in as them.
 * Filament blocks reuse of a code per secret, so after signing in as one seeded admin, wait for the
 * next code (up to 30 seconds) before signing in as another.
 * Safe to run repeatedly; refuses to run in production.
 */
class LocalTestUsersSeeder extends Seeder
{
    /**
     * Base32 secret shared by the seeded admins. Add it to an authenticator app to get sign-in codes.
     */
    public const AUTHENTICATOR_SECRET = 'JBSWY3DPEHPK3PXP';

    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('LocalTestUsersSeeder must never run in production.');
        }

        $this->call(AdminRoleSeeder::class);

        User::updateOrCreate(['email' => 'player@sportsbook.test'], [
            'name' => 'Test Player',
            'password' => self::PASSWORD,
            'email_verified_at' => now(),
        ]);

        $admins = [
            ['superadmin@sportsbook.test', 'Super Admin', AdminRole::SuperAdmin, true, true],
            ['manager@sportsbook.test', 'Manager', AdminRole::Manager, true, true],
            ['trader@sportsbook.test', 'Trader', AdminRole::Trader, true, true],
            ['finance@sportsbook.test', 'Finance', AdminRole::Finance, true, true],
            ['support@sportsbook.test', 'Support', AdminRole::Support, true, true],
            ['inactive@sportsbook.test', 'Inactive Admin', AdminRole::Support, false, true],
            ['newadmin@sportsbook.test', 'New Admin', AdminRole::Support, true, false],
        ];

        foreach ($admins as [$email, $name, $role, $isActive, $hasAuthenticator]) {
            $admin = Admin::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => self::PASSWORD,
                'is_active' => $isActive,
            ]);

            $admin->saveAppAuthenticationSecret($hasAuthenticator ? self::AUTHENTICATOR_SECRET : null);
            $admin->saveAppAuthenticationRecoveryCodes(null);

            if (! $admin->hasExactRoles([$role->value])) {
                $admin->syncRoles([$role->value]);
            }
        }
    }
}
