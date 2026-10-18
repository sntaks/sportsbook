<?php

namespace App\Enums;

enum AdminPermission: string
{
    case ManageAdmins = 'manage admins';
    case ViewActivityLog = 'view activity log';
    case ManageCompetitions = 'manage competitions';
    case ManageOdds = 'manage odds';
    case ViewPlayers = 'view players';
    case ManagePlayers = 'manage players';
    case ApproveWithdrawals = 'approve withdrawals';
    case AdjustBalances = 'adjust balances';
    case ReconcilePayments = 'reconcile payments';
    case ViewBets = 'view bets';
    case SettleBets = 'settle bets';
}
