<?php

namespace App\Http\Resources\V1;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A promotion. The body is only sent when one promotion is asked for.
 *
 * @mixin Promotion
 */
class PromotionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'summary' => $this->summary,
            /** Absolute URL of the banner image, about twice as wide as tall. */
            'image_url' => $this->imageUrl(),
            'starts_at' => $this->starts_at->toIso8601String(),
            /** Null when it runs until staff take it down. */
            'ends_at' => $this->ends_at?->toIso8601String(),
            /** The page on the website. */
            'url' => route('promotions.show', $this->resource),
            /** The body as Markdown, as staff wrote it. Only on a single promotion. */
            'body_markdown' => $this->when($request->routeIs('api.v1.promotions.show'), fn (): string => $this->body),
            /** The body as HTML, with any HTML staff typed and any unsafe links removed. Only on a single promotion. */
            'body_html' => $this->when($request->routeIs('api.v1.promotions.show'), fn (): string => $this->bodyHtml()->toHtml()),
        ];
    }
}
