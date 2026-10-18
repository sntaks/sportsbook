<?php

namespace App\Models;

use App\Enums\MpesaBalanceReportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One M-Pesa account balance check. Safaricom answers on the balance callback, which fills in the balances.
 *
 * @property int $id
 * @property string $account "paybill" or "b2c".
 * @property string|null $conversation_id
 * @property string|null $originator_conversation_id
 * @property MpesaBalanceReportStatus $status
 * @property list<array{name: string, currency: string, available: float}>|null $balances
 * @property string|null $result_description
 * @property int|null $requested_by_admin_id
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['account', 'conversation_id', 'originator_conversation_id', 'status', 'balances', 'result_description', 'requested_by_admin_id', 'received_at'])]
class MpesaBalanceReport extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MpesaBalanceReportStatus::class,
            'balances' => 'array',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id');
    }
}
