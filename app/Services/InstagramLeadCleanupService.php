<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstagramLeadCleanupService
{
    /**
     * Find lead IDs that originated from Instagram integrations and have no active transactions.
     *
     * @return int[]
     */
    public static function findTargetLeadIds(bool $includeAllInstagramSource = false): array
    {
        if (!Schema::hasTable('leads')) {
            return [];
        }

        $igChatLeadIds = [];
        if (Schema::hasTable('chat_messages') && Schema::hasColumn('chat_messages', 'platform')) {
            $igChatLeadIds = DB::table('chat_messages')
                ->where('platform', 'instagram')
                ->whereNotNull('lead_id')
                ->distinct()
                ->pluck('lead_id')
                ->all();
        }

        $query = DB::table('leads')->where('source', 'instagram');

        if ($includeAllInstagramSource) {
            $query->where(function ($q) {
                $q->whereNull('notes')
                    ->orWhere('notes', 'not like', '[Via WhatsApp API]%');
            });
        } else {
            $query->where(function ($q) use ($igChatLeadIds) {
                $q->where('notes', 'like', '[Via Meta Instagram Chat]%')
                    ->orWhere('notes', 'like', '[Via Meta Lead Ads Form]%');
                if (!empty($igChatLeadIds)) {
                    $q->orWhereIn('id', $igChatLeadIds);
                }
            });
        }

        foreach (['bookings', 'reservations', 'negotiations', 'client_balance_sheets'] as $protectedTable) {
            if (Schema::hasTable($protectedTable) && Schema::hasColumn($protectedTable, 'lead_id')) {
                $query->whereNotExists(function ($sub) use ($protectedTable) {
                    $sub->select(DB::raw(1))
                        ->from($protectedTable)
                        ->whereColumn("{$protectedTable}.lead_id", 'leads.id');
                });
            }
        }

        return $query->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Delete auto-imported Instagram leads and Instagram chat messages without touching other data.
     *
     * @return array{leads:int, chat_messages:int, lead_activities:int, follow_up_reminders:int}
     */
    public static function cleanup(bool $includeAllInstagramSource = false, bool $dryRun = false): array
    {
        $leadIds = static::findTargetLeadIds($includeAllInstagramSource);

        $counts = [
            'leads' => count($leadIds),
            'chat_messages' => 0,
            'lead_activities' => 0,
            'follow_up_reminders' => 0,
        ];

        $hasChatMessages = Schema::hasTable('chat_messages');
        $hasPlatformCol = $hasChatMessages && Schema::hasColumn('chat_messages', 'platform');
        $hasActivities = Schema::hasTable('lead_activities');
        $hasReminders = Schema::hasTable('follow_up_reminders');

        if ($dryRun) {
            if ($hasChatMessages && $hasPlatformCol) {
                $counts['chat_messages'] += DB::table('chat_messages')
                    ->where('platform', 'instagram')
                    ->whereNull('lead_id')
                    ->count();
            }

            foreach (array_chunk($leadIds, 500) as $chunk) {
                if ($hasChatMessages) {
                    $counts['chat_messages'] += DB::table('chat_messages')
                        ->whereIn('lead_id', $chunk)
                        ->count();
                }
                if ($hasActivities) {
                    $counts['lead_activities'] += DB::table('lead_activities')
                        ->whereIn('lead_id', $chunk)
                        ->count();
                }
                if ($hasReminders) {
                    $counts['follow_up_reminders'] += DB::table('follow_up_reminders')
                        ->whereIn('lead_id', $chunk)
                        ->count();
                }
            }

            return $counts;
        }

        DB::transaction(function () use ($leadIds, $hasChatMessages, $hasPlatformCol, $hasActivities, $hasReminders, &$counts) {
            if ($hasChatMessages && $hasPlatformCol) {
                $counts['chat_messages'] += DB::table('chat_messages')
                    ->where('platform', 'instagram')
                    ->whereNull('lead_id')
                    ->delete();
            }

            foreach (array_chunk($leadIds, 500) as $chunk) {
                if ($hasChatMessages) {
                    $counts['chat_messages'] += DB::table('chat_messages')
                        ->whereIn('lead_id', $chunk)
                        ->delete();
                }
                if ($hasActivities) {
                    $counts['lead_activities'] += DB::table('lead_activities')
                        ->whereIn('lead_id', $chunk)
                        ->delete();
                }
                if ($hasReminders) {
                    $counts['follow_up_reminders'] += DB::table('follow_up_reminders')
                        ->whereIn('lead_id', $chunk)
                        ->delete();
                }
                DB::table('leads')->whereIn('id', $chunk)->delete();
            }
        });

        return $counts;
    }
}
