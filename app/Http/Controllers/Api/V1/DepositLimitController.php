<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DepositLimitPeriod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateDepositLimitRequest;
use App\Http\Resources\V1\DepositLimitResource;
use App\Models\DepositLimit;
use App\Wallet\ResponsibleGambling\DepositAllowance;
use App\Wallet\ResponsibleGambling\SetDepositLimit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Responsible gambling
 */
class DepositLimitController extends Controller
{
    /**
     * Deposit limits.
     *
     * One entry per period (daily, weekly, monthly), with `amount_cents` null where no limit is set, and how much
     * the limits still allow right now.
     */
    public function index(Request $request, DepositAllowance $depositAllowance): JsonResponse
    {
        $user = $request->user();
        $limits = $user->depositLimits()->get()->keyBy(fn (DepositLimit $limit): string => $limit->period->value);

        return response()->json([
            'data' => collect(DepositLimitPeriod::cases())
                ->map(fn (DepositLimitPeriod $period): DepositLimitResource => DepositLimitResource::make(
                    $limits->get($period->value) ?? new DepositLimit(['user_id' => $user->id, 'period' => $period]),
                ))
                ->all(),
            /** Cents the limits still allow, or null when no limit is set. */
            'allowance_cents' => $depositAllowance->remaining($user),
            /** Hours a raise or removal waits before it applies. Cuts apply at once. */
            'raise_delay_hours' => (int) config('sportsbook.wallet.limit_raise_delay_hours'),
        ]);
    }

    /**
     * Set a deposit limit.
     *
     * `amount` in whole shillings, or null to remove the limit. Lowering a limit applies at once; raising or
     * removing one waits (see `pending_from`).
     */
    public function update(UpdateDepositLimitRequest $request, DepositLimitPeriod $period, SetDepositLimit $setDepositLimit): JsonResponse
    {
        return DepositLimitResource::make($setDepositLimit->handle($request->user(), $period, $request->input('amount')))
            ->response()
            ->setStatusCode(200);
    }
}
