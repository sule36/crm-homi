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
        $query = Lead::with(['assignedTo.brokerCompany', 'project', 'campaign', 'brokerCompany']);

        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        // Master lead sees their team's leads, sales agents see only own leads
        if ($isMasterLead) {
            $teamUserIds = User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
            $query->whereIn('assigned_to', $teamUserIds);
        } elseif ($user->hasRole('sales_agent') || $user->hasRole('broker')) {
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
            ->when($isMasterLead, function($q) use ($user) {
                $teamUserIds = User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $q->whereIn('assigned_to', $teamUserIds);
            })
            ->when($user->hasRole('sales_agent') && !$isMasterLead, fn ($q) => $q->where('assigned_to', $user->id))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $projects = Project::select('id', 'name')->get();
        $dutyAgents = [];
        try {
            foreach ($projects as $proj) {
                $da = \App\Models\ProjectDutySchedule::getDutyAgent($proj->id);
                if ($da) {
                    $dutyAgents[$proj->id] = [
                        'id' => $da->id,
                        'name' => $da->name,
                        'phone' => $da->phone,
                        'agent_type' => $da->agent_type,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error getting duty agents for leads index: ' . $e->getMessage());
        }

        return Inertia::render('Leads/Index', [
            'leads' => $leads,
            'pipeline' => $pipeline,
            'filters' => $request->only(['search', 'status', 'project_id', 'source', 'assigned_to']),
            'projects' => $projects,
            'agents' => User::with('brokerCompany:id,name,code')->select('id', 'name', 'agent_type', 'broker_company_id', 'project_id')->get(),
            'broker_companies' => \App\Models\BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
            'dutyAgents' => $dutyAgents,
        ]);
    }

    public function create()
    {
        return Inertia::render('Leads/Form', [
            'projects' => Project::select('id', 'name')->where('status', 'active')->get(),
            'agents' => User::with('brokerCompany:id,name,code')->select('id', 'name', 'agent_type', 'broker_company_id')->get(),
            'broker_companies' => \App\Models\BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
        ]);
    }

    public function edit(Lead $lead)
    {
        return Inertia::render('Leads/Form', [
            'lead' => $lead,
            'projects' => Project::select('id', 'name')->where('status', 'active')->get(),
            'agents' => User::with('brokerCompany:id,name,code')->select('id', 'name', 'agent_type', 'broker_company_id')->get(),
            'broker_companies' => \App\Models\BrokerCompany::where('status', 'active')->select('id', 'name', 'code')->get(),
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
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'source' => 'required|in:facebook,instagram,google,tiktok,walk_in,referral,broker,website,agent,other',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|array',
            'nik' => 'nullable|string|max:30',
            'npwp' => 'nullable|string|max:30',
            'address' => 'nullable|string',
            'job' => 'nullable|string|max:255',
            'budget' => 'nullable|numeric|min:0',
            'preferred_type' => 'nullable|string|max:255',
            'inhouse_pic_id' => 'nullable|exists:users,id',
        ]);

        $validated['status'] = 'new';
        $validated['score'] = 5;
        $validated['company_id'] = auth()->user()?->company_id ?? 1;

        // Smart Auto-Assign:
        if (empty($validated['assigned_to'])) {
            // Prioritize today's In-House Duty Agent for Walk-In leads!
            if (($validated['source'] ?? '') === 'walk_in' && !empty($validated['project_id'])) {
                $dutyAgent = \App\Models\ProjectDutySchedule::getDutyAgent((int)$validated['project_id']);
                if ($dutyAgent) {
                    $validated['assigned_to'] = $dutyAgent->id;
                }
            }
            if (empty($validated['assigned_to'])) {
                $validated['assigned_to'] = $this->getNextAgent($validated['project_id'] ?? null);
            }
        }

        // For broker / agency leads, auto-assign today's duty agent as in-house PIC if not provided
        if (in_array($validated['source'] ?? '', ['broker', 'agency', 'agent', 'independent']) && empty($validated['inhouse_pic_id']) && !empty($validated['project_id'])) {
            $dutyAgent = \App\Models\ProjectDutySchedule::getDutyAgent((int)$validated['project_id']);
            if ($dutyAgent) {
                $validated['inhouse_pic_id'] = $dutyAgent->id;
            }
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
        $relations = [
            'assignedTo.brokerCompany', 'project', 'campaign', 'brokerCompany',
            'activities.user', 'reminders', 
            'bookings.unit.project', 'bookings.unit.unitType', 'bookings.paymentSchedules.transactions', 'bookings.transactions', 'bookings.bookedBy', 'bookings.approvedBy',
        ];

        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('leads', 'inhouse_pic_id')) {
                $relations[] = 'inhousePic';
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'inhouse_pic_id')) {
                $relations[] = 'bookings.inhousePic';
            }
        } catch (\Throwable $e) {}

        if (\Illuminate\Support\Facades\Schema::hasTable('negotiations')) {
            $relations[] = 'negotiations.unit.project';
            $relations[] = 'negotiations.unit.unitType';
            $relations[] = 'negotiations.creator';
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('reservations')) {
            $relations[] = 'reservations.unit.project';
            $relations[] = 'reservations.unit.unitType';
            $relations[] = 'reservations.agentCoordinator';
        }

        $lead->load($relations);

        $units = \App\Models\Unit::when($lead->project_id, fn ($q) => $q->where('project_id', $lead->project_id))
            ->where('status', '!=', 'sold')
            ->select('id', 'block', 'number', 'floor', 'final_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name,address', 'unitType:id,name,land_area,building_area'])
            ->orderBy('block')
            ->orderByRaw('CAST(number AS UNSIGNED) ASC')
            ->get();

        $dutyAgent = null;
        try {
            if ($lead->project_id) {
                $dutyAgent = \App\Models\ProjectDutySchedule::getDutyAgent((int)$lead->project_id);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error loading duty agent in show: ' . $e->getMessage());
        }

        return Inertia::render('Leads/Show', [
            'lead' => $lead,
            'units' => $units,
            'projects' => \App\Models\Project::select('id', 'name', 'code')->get(),
            'bankAccounts' => \App\Models\BankAccount::where('is_active', true)->select('id', 'name', 'account_number', 'bank_name', 'current_balance')->get(),
            'agents' => User::with('brokerCompany:id,name,code')
                ->select('id', 'name', 'email', 'phone', 'agent_type', 'broker_company_id', 'project_id')
                ->orderBy('name')
                ->get(),
            'brokerCompanies' => \App\Models\BrokerCompany::when(\Illuminate\Support\Facades\Schema::hasColumn('broker_companies', 'status'), fn ($q) => $q->where('status', 'active'))
                ->select('id', 'name', 'code')
                ->orderBy('name')
                ->get(),
            'dutyAgent' => $dutyAgent ? [
                'id' => $dutyAgent->id,
                'name' => $dutyAgent->name,
                'phone' => $dutyAgent->phone,
                'agent_type' => $dutyAgent->agent_type,
            ] : null,
        ]);
    }

    public function update(Request $request, Lead $lead)
    {
        if (is_array($request->input('assigned_to'))) {
            $request->merge(['assigned_to' => $request->input('assigned_to.id')]);
        }
        if (is_array($request->input('inhouse_pic_id'))) {
            $request->merge(['inhouse_pic_id' => $request->input('inhouse_pic_id.id')]);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'nullable|email|max:255',
            'identity_number' => 'nullable|string|max:50',
            'npwp' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'job' => 'nullable|string|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_to' => 'nullable|exists:users,id',
            'inhouse_pic_id' => 'nullable|exists:users,id',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'source' => 'sometimes|in:facebook,instagram,google,tiktok,walk_in,referral,broker,website,other',
            'status' => 'sometimes|in:new,contacted,visited,negotiation,reservation,booking,won,lost',
            'notes' => 'nullable|string',
            'lost_reason' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        // Auto-fill broker_company_id from assigned agent if empty
        if (!empty($validated['assigned_to']) && empty($validated['broker_company_id'])) {
            $agentUser = User::find($validated['assigned_to']);
            if ($agentUser && $agentUser->broker_company_id) {
                $validated['broker_company_id'] = $agentUser->broker_company_id;
            }
        }

        $old = $lead->toArray();

        // Log agent assignment change
        if (array_key_exists('assigned_to', $validated) && $validated['assigned_to'] != $lead->assigned_to) {
            $newAgent = !empty($validated['assigned_to']) ? User::find($validated['assigned_to']) : null;
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "Agen penanggung jawab diubah ke: " . ($newAgent ? $newAgent->name : 'Belum Ditugaskan'),
            ]);
        }

        if (array_key_exists('inhouse_pic_id', $validated) && $validated['inhouse_pic_id'] != $lead->inhouse_pic_id) {
            $newPic = !empty($validated['inhouse_pic_id']) ? User::find($validated['inhouse_pic_id']) : null;
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "Sales In-House Pendamping diubah ke: " . ($newPic ? $newPic->name : 'Tidak Ada'),
            ]);
        }

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

        // Sync agent with active bookings if booked_by is empty
        if (!empty($validated['assigned_to'])) {
            $lead->bookings()->whereNull('booked_by')->update(['booked_by' => $validated['assigned_to']]);
        }

        // Sync buyer details & SPR signature name with active bookings
        foreach ($lead->bookings as $booking) {
            $bUpdates = [];
            if (isset($validated['name'])) {
                if (empty($booking->secondary_name)) {
                    if ($booking->sig4_name && $booking->sig4_name !== $validated['name']) {
                        // The client name was changed to another person! Reset previous digital signature
                        $bUpdates['sig4_image'] = null;
                        $bUpdates['customer_signed_at'] = null;
                    }
                    $bUpdates['sig4_name'] = $validated['name'];
                }
            }
            if (isset($validated['identity_number'])) {
                $bUpdates['buyer_nik'] = $validated['identity_number'];
            }
            if (isset($validated['npwp'])) {
                $bUpdates['buyer_npwp'] = $validated['npwp'];
            }
            if (isset($validated['address'])) {
                $bUpdates['buyer_address'] = $validated['address'];
            }
            if (isset($validated['job'])) {
                $bUpdates['buyer_job'] = $validated['job'];
            }
            if (!empty($bUpdates)) {
                $booking->update($bUpdates);
            }
        }

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
            'status' => 'required|in:new,contacted,visited,negotiation,reservation,booking,won,lost',
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
