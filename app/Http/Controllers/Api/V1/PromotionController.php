<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @tags Promotions
 */
class PromotionController extends Controller
{
    /**
     * Running promotions.
     *
     * The promotions players can see now, in the order staff set. Ask for one by `slug` for its full text.
     *
     * @unauthenticated
     */
    public function index(): AnonymousResourceCollection
    {
        return PromotionResource::collection(Promotion::query()->live()->get());
    }

    /**
     * A promotion.
     *
     * With its full text as Markdown and as HTML. A 404 means there is no running promotion with that slug.
     *
     * @unauthenticated
     */
    public function show(Promotion $promotion): PromotionResource
    {
        abort_unless($promotion->isLive(), 404);

        return PromotionResource::make($promotion);
    }
}
