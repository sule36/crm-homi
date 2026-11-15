<?php

namespace App\Console\Commands;

use App\Services\InstagramLeadCleanupService;
use Illuminate\Console\Command;

class CleanupInstagramLeads extends Command
{
    protected $signature = 'leads:cleanup-instagram
        {--all-source : Also delete manual/untagged source=instagram leads without transactions (still excludes WhatsApp leads)}
        {--dry-run : Preview counts without deleting anything}';

    protected $description = 'Delete incoming leads and messages from Instagram without touching other data or leads with transactions';

    public function handle(): int
    {
        $allSource = (bool) $this->option('all-source');
        $dryRun = (bool) $this->option('dry-run');

        $counts = InstagramLeadCleanupService::cleanup($allSource, $dryRun);

        $prefix = $dryRun ? '[DRY RUN] Akan dihapus' : 'Berhasil dihapus';
        $this->info("{$prefix}: {$counts['leads']} leads, {$counts['chat_messages']} chat_messages, {$counts['lead_activities']} lead_activities, {$counts['follow_up_reminders']} follow_up_reminders.");

        return self::SUCCESS;
    }
}

