<?php

namespace App\Listeners;

use App\Models\Admin;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

/**
 * Writes role grants and removals on admin accounts to the audit log.
 */
class RecordAdminRoleChange
{
    /**
     * Record roles granted to an admin.
     */
    public function handleAttached(RoleAttachedEvent $event): void
    {
        $this->record($event->model, $event->rolesOrIds, 'role-attached', 'Role granted');
    }

    /**
     * Record roles removed from an admin.
     */
    public function handleDetached(RoleDetachedEvent $event): void
    {
        $this->record($event->model, $event->rolesOrIds, 'role-detached', 'Role removed');
    }

    /**
     * @param  mixed  $rolesOrIds  Role models, role ids, or a collection of either, as passed by spatie/laravel-permission.
     */
    private function record(mixed $model, mixed $rolesOrIds, string $event, string $description): void
    {
        if (! $model instanceof Admin) {
            return;
        }

        $roleIds = collect(Arr::wrap($rolesOrIds instanceof Collection ? $rolesOrIds->all() : $rolesOrIds))
            ->map(fn (mixed $role): mixed => $role instanceof Role ? $role->getKey() : $role);

        activity('admin')
            ->performedOn($model)
            ->event($event)
            ->withProperties(['roles' => Role::whereKey($roleIds)->pluck('name')->all()])
            ->log($description);
    }
}
