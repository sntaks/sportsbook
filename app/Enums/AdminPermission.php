<?php

namespace App\Enums;

enum AdminPermission: string
{
    case ManageAdmins = 'manage admins';
    case ViewActivityLog = 'view activity log';
    case ManageCompetitions = 'manage competitions';
    case ManageOdds = 'manage odds';
}
