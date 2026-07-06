<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Carbon;

class AgentMonitorController extends Controller
{
    public function index(Request $request)
    {
        $showAllTime = $request->boolean('all_time', false);
        
        $agents = User::role('sales_agent')
            ->where('status', 'active')
            ->withCount(['leads as active_leads_count' => fn ($q) => $q->whereNotIn('status', ['won', 'lost'])])
            ->with(['leads' => function ($query) use ($showAllTime) {
                if (!$showAllTime) {
                    $query->whereDate('created_at', today());
                }
                $query->select('id', 'assigned_to', 'name', 'source', 'status', 'created_at')
                      ->orderBy('created_at', 'desc');
            }])
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'is_accepting_leads' => $agent->is_accepting_leads,
                    'lead_capacity' => $agent->lead_capacity,
                    'active_leads_count' => $agent->active_leads_count,
                    'leads' => $agent->leads->map(function ($lead) {
                        return [
                            'id' => $lead->id,
                            'name' => $lead->name,
                            'source' => $lead->source,
                            'status' => $lead->status,
                            'time' => $lead->created_at->format('H:i:s'),
                            'date' => $lead->created_at->format('Y-m-d'),
                        ];
                    }),
                ];
            });

        return Inertia::render('Users/Monitor', [
            'agents' => $agents,
            'showAllTime' => $showAllTime,
        ]);
    }

    public function toggleAssignment(Request $request, User $user)
    {
        $validated = $request->validate([
            'is_accepting_leads' => 'required|boolean',
        ]);

        $user->update(['is_accepting_leads' => $validated['is_accepting_leads']]);

        return back()->with('success', 'Status penerimaan lead diperbarui.');
    }

    public function updateCapacity(Request $request, User $user)
    {
        $validated = $request->validate([
            'lead_capacity' => 'required|integer|min:0',
        ]);

        $user->update(['lead_capacity' => $validated['lead_capacity']]);

        return back()->with('success', 'Kapasitas lead diperbarui.');
    }
}
