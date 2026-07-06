<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:check-stale-leads')]
#[Description('Check for leads that have not been contacted recently and create reminders')]
class CheckStaleLeads extends Command
{
    public function handle()
    {
        $staleDays = 7;
        
        $staleLeads = \App\Models\Lead::whereNotIn('status', ['won', 'lost'])
            ->whereNotNull('assigned_to')
            ->where(function ($query) use ($staleDays) {
                $query->where('last_contacted_at', '<', now()->subDays($staleDays))
                      ->orWhereNull('last_contacted_at');
            })
            ->get();

        $count = 0;
        foreach ($staleLeads as $lead) {
            // Check if there is already a pending reminder for today or future
            $hasReminder = \App\Models\FollowUpReminder::where('lead_id', $lead->id)
                ->where('status', 'pending')
                ->whereDate('remind_at', '>=', today())
                ->exists();

            if (!$hasReminder) {
                \App\Models\FollowUpReminder::create([
                    'lead_id' => $lead->id,
                    'user_id' => $lead->assigned_to,
                    'remind_at' => now()->addDay(), // Remind tomorrow
                    'message' => 'Lead ini belum dihubungi lebih dari ' . $staleDays . ' hari. Segera tindak lanjuti!',
                ]);
                $count++;
            }
        }

        $this->info("Created reminders for {$count} stale leads.");
    }
}
