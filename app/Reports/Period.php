<?php

namespace App\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Throwable;

/**
 * A run of whole EAT calendar days, first to last inclusive, never past today. Reports query it as the UTC instants
 * from the start of the first day to the start of the day after the last, or to an earlier cut-off when one is set.
 */
final readonly class Period
{
    private function __construct(
        public CarbonImmutable $firstDay,
        public CarbonImmutable $lastDay,
        public ?CarbonImmutable $cutOff = null,
    ) {}

    /**
     * The given number of days, ending today.
     */
    public static function lastDays(int $days): self
    {
        $today = self::today();

        return new self($today->subDays(max(1, $days) - 1), $today);
    }

    /**
     * Days typed as Y-m-d. A missing last day means today and a missing first day the 30 days to the last; a reversed
     * range is put the right way round, the last day is never past today, and the range keeps at most its last
     * max_days days.
     */
    public static function between(?string $firstDay, ?string $lastDay): self
    {
        $today = self::today();
        $last = self::parse($lastDay) ?? $today;
        $first = self::parse($firstDay) ?? $last->subDays(29);

        if ($first->greaterThan($last)) {
            [$first, $last] = [$last, $first];
        }

        $last = $last->min($today);
        $first = $first->min($last)->max($last->subDays((int) config('sportsbook.reports.max_days') - 1));

        return new self($first, $last);
    }

    /**
     * The same number of days just before this period. When this period runs to today, the earlier one stops at the
     * same time of day, so a day still under way is compared with the same hours of the day before it.
     */
    public function previous(): self
    {
        $days = $this->dayCount();
        $cutOff = $this->lastDay->equalTo(self::today()) ? Date::now()->toImmutable()->subDays($days) : null;

        return new self($this->firstDay->subDays($days), $this->lastDay->subDays($days), $cutOff);
    }

    public function start(): CarbonImmutable
    {
        return $this->firstDay->setTimezone('UTC');
    }

    public function end(): CarbonImmutable
    {
        $end = $this->lastDay->addDay()->setTimezone('UTC');

        return $this->cutOff === null ? $end : $end->min($this->cutOff->setTimezone('UTC'));
    }

    public function dayCount(): int
    {
        return (int) $this->firstDay->diffInDays($this->lastDay) + 1;
    }

    /**
     * Each day as Y-m-d, newest first.
     *
     * @return list<string>
     */
    public function days(): array
    {
        $days = [];

        for ($day = $this->lastDay; $day->greaterThanOrEqualTo($this->firstDay); $day = $day->subDay()) {
            $days[] = $day->toDateString();
        }

        return $days;
    }

    /**
     * Minutes EAT is ahead of UTC. Nairobi keeps no daylight saving, so one offset holds for every day.
     */
    public function utcOffsetMinutes(): int
    {
        return (int) $this->firstDay->utcOffset();
    }

    private static function today(): CarbonImmutable
    {
        return Date::now()->setTimezone((string) config('sportsbook.timezone'))->startOfDay();
    }

    private static function parse(?string $day): ?CarbonImmutable
    {
        if (blank($day)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', substr($day, 0, 10), (string) config('sportsbook.timezone')) ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}
