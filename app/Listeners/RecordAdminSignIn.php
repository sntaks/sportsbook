<?php

namespace App\Listeners;

use App\Models\Admin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;

/**
 * Writes every admin sign-in attempt to the audit log. Player sign-ins on the web guard are ignored.
 */
class RecordAdminSignIn
{
    /**
     * Record a successful admin sign-in and stamp the account's last login time.
     */
    public function handleLogin(Login $event): void
    {
        if ($event->guard !== 'admin' || ! $event->user instanceof Admin) {
            return;
        }

        $event->user->forceFill(['last_login_at' => now()])->save();

        activity('admin')
            ->causedBy($event->user)
            ->performedOn($event->user)
            ->event('login')
            ->withProperties(['ip' => request()->ip()])
            ->log('Signed in');
    }

    /**
     * Record a failed admin sign-in. The attempted email is kept; the attempted password never is.
     */
    public function handleFailed(Failed $event): void
    {
        if ($event->guard !== 'admin') {
            return;
        }

        $logger = activity('admin')
            ->event('login-failed')
            ->withProperties([
                'email' => $event->credentials['email'] ?? null,
                'ip' => request()->ip(),
            ]);

        if ($event->user instanceof Admin) {
            $logger->performedOn($event->user);
        }

        $logger->causedByAnonymous()->log('Failed sign-in');
    }
}
