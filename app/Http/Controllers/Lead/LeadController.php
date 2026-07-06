<?php

namespace App\Http\Controllers\Lead;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\FollowUpReminder;
use App\Models\Project;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Lead::with(['assignedTo', 'project', 'campaign']);

        // Sales agents can only see their own leads
        if ($user->hasRole('sales_agent') || $user->hasRole('broker')) {
            $query->where('assigned_to', $user->id);
        }

        $leads = $query
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->source, fn ($q, $s) => $q->where('source', $s))
            ->when($request->assigned_to, fn ($q, $a) => $q->where('assigned_to', $a))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Pipeline counts
        $pipeline = Lead::query()
            ->when($user->hasRole('sales_agent'), fn ($q) => $q->where('assigned_to', $user->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'pipeline' => $pipeline,
            'filters' => $request->only(['search', 'status', 'project_id', 'source', 'assigned_to']),
            'projects' => Project::select('id', 'name')->get(),
            'agents' => User::role(['sales_agent', 'sales_manager'])->select('id', 'name')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Leads/Form', [
            'projects' => Project::select('id', 'name')->where('status', 'active')->get(),
            'agents' => User::role(['sales_agent', 'sales_manager'])->select('id', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'source' => 'required|in:facebook,instagram,google,tiktok,walk_in,referral,broker,website,other',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        $validated['status'] = 'new';
        $validated['score'] = 5;

        // Smart Auto-Assign: Consider capacity and workload
        if (empty($validated['assigned_to'])) {
            $validated['assigned_to'] = $this->getNextAgent($validated['project_id'] ?? null);
        }

        $lead = Lead::create($validated);

        // Log activity
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => 'Lead baru ditambahkan ke sistem.',
        ]);

        $lead->recalculateScore();
        AuditLog::record('created', $lead, null, $lead->toArray());

        return redirect()->route('leads.index')->with('success', 'Lead berhasil ditambahkan.');
    }

    public function show(Lead $lead)
    {
        $lead->load([
            'assignedTo', 'project', 'campaign', 'brokerCompany',
            'activities.user', 'reminders', 'bookings.unit',
        ]);

        return Inertia::render('Leads/Show', [
            'lead' => $lead,
            'agents' => User::role(['sales_agent', 'sales_manager'])->select('id', 'name')->get(),
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'source' => 'sometimes|in:facebook,instagram,google,tiktok,walk_in,referral,broker,website,other',
            'status' => 'sometimes|in:new,contacted,visited,negotiation,booking,won,lost',
            'notes' => 'nullable|string',
            'lost_reason' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        $old = $lead->toArray();

        // Log status change
        if (isset($validated['status']) && $validated['status'] !== $lead->status) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'status_change',
                'description' => "Status berubah dari {$lead->status} ke {$validated['status']}",
                'old_status' => $lead->status,
                'new_status' => $validated['status'],
            ]);
        }

        $lead->update($validated);
        $lead->recalculateScore();
        AuditLog::record('updated', $lead, $old, $validated);

        return back()->with('success', 'Lead berhasil diperbarui.');
    }

    public function destroy(Lead $lead)
    {
        AuditLog::record('deleted', $lead, $lead->toArray());
        $lead->delete();
        return redirect()->route('leads.index')->with('success', 'Lead berhasil dihapus.');
    }

    // Add activity to a lead
    public function addActivity(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'type' => 'required|in:call,whatsapp,email,visit,meeting,note',
            'description' => 'required|string|max:1000',
        ]);

        $activity = LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => $validated['type'],
            'description' => $validated['description'],
            'completed_at' => now(),
        ]);

        $lead->update(['last_contacted_at' => now()]);
        $lead->recalculateScore();

        return back()->with('success', 'Aktivitas dicatat.');
    }

    // Set follow-up reminder
    public function addReminder(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'remind_at' => 'required|date|after:now',
            'message' => 'nullable|string|max:500',
        ]);

        FollowUpReminder::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'remind_at' => $validated['remind_at'],
            'message' => $validated['message'],
        ]);

        $lead->recalculateScore();

        return back()->with('success', 'Reminder diset.');
    }

    public function completeReminder(FollowUpReminder $reminder)
    {
        if ($reminder->user_id !== auth()->id()) {
            abort(403);
        }

        $reminder->update(['status' => 'completed']);
        $reminder->lead->recalculateScore();

        return back()->with('success', 'Pengingat ditandai selesai.');
    }

    public function pipeline(Request $request)
    {
        $user = auth()->user();
        $query = Lead::with(['assignedTo', 'project']);

        if ($user->hasRole('sales_agent')) {
            $query->where('assigned_to', $user->id);
        }

        $leads = $query->get()->groupBy('status');

        return Inertia::render('Leads/Pipeline', [
            'leadsByStatus' => $leads
        ]);
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,visited,negotiation,booking,won,lost',
        ]);

        $oldStatus = $lead->status;
        $lead->update(['status' => $validated['status']]);
        $lead->recalculateScore();

        // Log status change activity
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => "Status diupdate via Pipeline dari {$oldStatus} ke {$validated['status']}",
        ]);

        return back()->with('success', 'Status diperbarui.');
    }

    // Smart lead assignment
    private function getNextAgent(?int $projectId): ?int
    {
        $query = User::role('sales_agent')->where('status', 'active')->where('is_accepting_leads', true);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        // Get agents with their active lead count
        $agents = $query->withCount(['leads as active_leads' => fn ($q) => $q->whereNotIn('status', ['won', 'lost'])])->get();

        // Filter agents who have capacity and sort by lowest workload ratio
        $availableAgent = $agents->filter(fn ($agent) => $agent->active_leads < ($agent->lead_capacity ?? 50))
            ->sortBy(fn ($agent) => $agent->active_leads / max(1, $agent->lead_capacity ?? 50))
            ->first();

        // Fallback: if everyone is full, just give it to the one with absolute lowest leads
        if (!$availableAgent) {
            $availableAgent = $agents->sortBy('active_leads')->first();
        }

        return $availableAgent?->id;
    }
}
