<?php

namespace App\Enums;

enum MpesaBalanceReportStatus: string
{
    case Requested = 'requested';
    case Received = 'received';
    case Failed = 'failed';
}
