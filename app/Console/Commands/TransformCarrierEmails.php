<?php

namespace App\Console\Commands;

use App\Models\Carrier;
use Illuminate\Console\Command;

class TransformCarrierEmails extends Command
{
    protected $signature = 'carrier:transform-emails
                            {--dry-run : Show what would be changed without saving}
                            {--force   : Run without confirmation prompt}';

    protected $description = 'Transform carrier email field (comma → semicolon, trim, etc.)';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');

        if (! $force && ! $dryRun) {
            if (! $this->confirm('This will modify carrier emails. Continue?')) {
                $this->info('Cancelled.');

                return self::SUCCESS;
            }
        }

        $carriers = Carrier::whereNotNull('emails')
            ->where('emails', 'like', '%,%')
            ->orWhere('emails', 'like', '% ;%')
            ->orWhereRaw('TRIM(emails) != emails')
            ->get();

        if ($carriers->isEmpty()) {
            $this->info('No carriers need transformation.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Short Code', 'Current emails', 'New emails'],
            $carriers->map(fn ($c) => [
                $c->id,
                $c->short_code,
                $c->emails,
                $this->normalizeEmails($c->emails),
            ])
        );

        if ($dryRun) {
            $this->info('Dry run — no changes made.');

            return self::SUCCESS;
        }

        $updated = 0;

        foreach ($carriers as $carrier) {
            $newEmails = $this->normalizeEmails($carrier->emails);

            if ($newEmails !== $carrier->emails) {
                $carrier->update(['emails' => $newEmails]);
                $updated++;
            }
        }

        $this->info("Updated {$updated} carriers.");

        return self::SUCCESS;
    }

    private function normalizeEmails(?string $emails): ?string
    {
        if (! $emails) {
            return null;
        }

        // Split on comma or semicolon, trim each, remove empty, join with semicolon
        $list = collect(explode(',', $emails))
            ->merge(explode(';', $emails))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->unique()
            ->values();

        return $list->isEmpty() ? null : $list->implode('; ');
    }
}
