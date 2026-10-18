<?php

namespace App\Filament;

use App\Models\Admin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The signed-in admin, and running an admin action so that a refusal from the domain (a ValidationException)
 * shows as a notification instead of an error page.
 */
class CurrentAdmin
{
    public static function get(): Admin
    {
        $admin = Filament::auth()->user();

        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }

    /**
     * Run the action and notify success, or notify why it was refused.
     *
     * @param  callable(Admin): mixed  $action
     */
    public static function run(callable $action, string $success): void
    {
        try {
            $action(self::get());
        } catch (ValidationException $e) {
            Notification::make()->title(__('Not done'))->body(collect($e->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title($success)->success()->send();
    }
}
