<?php

namespace App\Enums;

enum AdminPermission: string
{
    case ManageAdmins = 'manage admins';
    case ViewActivityLog = 'view activity log';
}
