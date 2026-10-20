<?php

namespace App\Policies;

use App\Enums\AdminPermission;
use App\Models\Admin;
use Filament\Actions\Exports\Models\Export;

/**
 * An export file can be downloaded only by the admin who ran it, and only while they may still export data.
 */
class ExportPolicy
{
    public function view(Admin $admin, Export $export): bool
    {
        return $admin->can(AdminPermission::ExportData->value) && $export->user()->is($admin);
    }
}
