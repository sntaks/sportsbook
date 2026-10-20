<?php

namespace App\Console\Commands;

use Filament\Actions\Exports\Models\Export;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('exports:prune')]
#[Description('Delete staff export files and their records once they are older than the retention period')]
class PruneExports extends Command
{
    /**
     * Exports carry players' phone numbers, so they are not kept longer than sportsbook.exports.retention_days.
     */
    public function handle(): int
    {
        $pruned = 0;

        Export::query()
            ->where('created_at', '<', now()->subDays((int) config('sportsbook.exports.retention_days')))
            ->each(function (Export $export) use (&$pruned): void {
                $export->deleteFileDirectory();
                $export->delete();
                $pruned++;
            });

        $this->info("Deleted {$pruned} exports.");

        return self::SUCCESS;
    }
}
