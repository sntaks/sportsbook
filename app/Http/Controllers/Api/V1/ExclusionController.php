<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExclusionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExclusionRequest;
use App\Http\Resources\V1\UserResource;
use App\Wallet\ResponsibleGambling\ExcludePlayer;
use Illuminate\Http\JsonResponse;

/**
 * @tags Responsible gambling
 */
class ExclusionController extends Controller
{
    /**
     * Take a break.
     *
     * Stops deposits and betting until the break ends; withdrawals still work. A break cannot be cancelled or
     * shortened, only made longer. `duration` is `24h`, `7d` or `30d` for a cool-off and `6m`, `1y` or `5y` for a
     * self-exclusion. The player's password confirms it.
     */
    public function store(StoreExclusionRequest $request, ExcludePlayer $excludePlayer): JsonResponse
    {
        $user = $excludePlayer->handle(
            $request->user(),
            $request->enum('type', ExclusionType::class),
            $request->string('duration')->toString(),
        );

        return UserResource::make($user)->response()->setStatusCode(201);
    }
}
