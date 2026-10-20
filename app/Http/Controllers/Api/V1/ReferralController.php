<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Promotions\ReferralCodes;
use App\Promotions\ReferralSummary;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags Referrals
 */
class ReferralController extends Controller
{
    /**
     * My invites.
     *
     * The player's invite code and link, the rules, how many rewards they have had this month against the limit, and
     * their latest 20 invited friends (numbers masked). Friends sign up with the code as `referral_code`, or through
     * the link. A friend's `status` is `waiting` (still has time to qualify), `expired`, `rewarded`, `capped` (over the
     * monthly limit) or `ineligible` (betting was paused on this account when they qualified). A 404 means
     * invites are switched off.
     */
    public function show(Request $request): JsonResponse
    {
        abort_unless(ReferralCodes::enabled(), 404);

        $summary = ReferralSummary::for($request->user());

        return response()->json([
            'code' => $summary->code,
            'link' => $summary->link,
            'terms' => ReferralSummary::terms(),
            'rewards_this_month' => $summary->rewardsThisMonth,
            'monthly_cap' => $summary->monthlyCap,
            'invited_count' => $summary->invitedCount,
            'friends' => $summary->friends->map(fn (User $friend): array => [
                'phone' => PhoneNumber::mask($friend->phone),
                'joined_at' => $friend->created_at?->toIso8601String(),
                'status' => ReferralSummary::friendStatus($friend),
                'status_label' => ReferralSummary::friendStatusLabel($friend),
                /** When a waiting friend's time to qualify runs out. */
                'qualify_by' => ReferralSummary::deadlineFor($friend)->toIso8601String(),
            ])->all(),
        ]);
    }
}
