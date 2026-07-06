<?php

namespace App\Services;

use App\Models\User;
use App\Models\Lead;

class LeadAssignmentService
{
    /**
     * Assign a lead to a sales agent using Round Robin logic.
     */
    public static function assign(Lead $lead)
    {
        $query = User::role('sales_agent')->where('status', 'active')->where('is_accepting_leads', true);
        if ($lead->project_id) {
            $query->where('project_id', $lead->project_id);
        }

        // Get agents with their active lead count
        $agents = $query->withCount(['leads as active_leads' => fn ($q) => $q->whereNotIn('status', ['won', 'lost'])])->get();

        if ($agents->isEmpty()) {
            return null;
        }

        // Filter agents who have capacity and sort by lowest workload ratio
        $targetAgent = $agents->filter(fn ($agent) => $agent->active_leads < ($agent->lead_capacity ?? 50))
            ->sortBy(fn ($agent) => $agent->active_leads / max(1, $agent->lead_capacity ?? 50))
            ->first();

        // Fallback: if everyone is full, just give it to the one with absolute lowest leads
        if (!$targetAgent) {
            $targetAgent = $agents->sortBy('active_leads')->first();
        }

        if ($targetAgent) {
            $lead->update([
                'assigned_to' => $targetAgent->id,
                'status' => 'new'
            ]);
        }

        return $targetAgent;
    }
}
