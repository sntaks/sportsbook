<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A promotion page written by staff. It shows on the site while published and between starts_at and ends_at.
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $summary
 * @property string $body Markdown.
 * @property string|null $image_path On the public disk.
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property bool $is_published
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['title', 'slug', 'summary', 'body', 'image_path', 'starts_at', 'ends_at', 'is_published', 'sort_order'])]
class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory, LogsActivity;

    /**
     * Remove the image with the promotion.
     */
    protected static function booted(): void
    {
        static::deleted(function (self $promotion): void {
            if ($promotion->image_path !== null) {
                Storage::disk('public')->delete($promotion->image_path);
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Promotions players can see now, in the order staff set, newest first within it.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('is_published', true)
            ->where('starts_at', '<=', now())
            ->where(fn (Builder $ends) => $ends->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderBy('sort_order')
            ->orderByDesc('starts_at')
            ->orderByDesc('id');
    }

    public function isLive(): bool
    {
        return $this->is_published && ! $this->starts_at->isFuture() && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Draft, scheduled, live or ended, for the admin list.
     */
    public function state(): string
    {
        return match (true) {
            ! $this->is_published => 'draft',
            $this->starts_at->isFuture() => 'scheduled',
            $this->ends_at !== null && ! $this->ends_at->isFuture() => 'ended',
            default => 'live',
        };
    }

    public function imageUrl(): ?string
    {
        return $this->image_path === null ? null : Storage::disk('public')->url($this->image_path);
    }

    /**
     * The body as HTML. Any HTML written into the Markdown is stripped and unsafe links (javascript: and the like)
     * are dropped, so a page can never run a script.
     */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * Record who published, scheduled or changed a page players see.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('promotions')
            ->logOnly(['title', 'slug', 'is_published', 'starts_at', 'ends_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn (string $eventName): string => "Promotion {$eventName}");
    }
}
