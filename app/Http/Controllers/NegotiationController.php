<?php

namespace App\Http\Controllers;

use App\Models\Negotiation;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Unit;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NegotiationController extends Controller
{
    /**
     * CRM Internal: List all negotiations
     */
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('negotiations')) {
            return Inertia::render('Negotiations/Index', [
                'negotiations' => ['data' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'last_page' => 1, 'links' => []],
                'stats' => ['total' => 0, 'pending' => 0, 'counter_offer' => 0, 'approved' => 0, 'rejected' => 0, 'converted' => 0],
                'filters' => $request->only(['status', 'project_id', 'created_by', 'search']),
                'projects' => Project::select('id', 'name')->get(),
                'agents' => [],
                'units' => [],
                'leads' => [],
            ]);
        }

        $user = auth()->user();

        $query = Negotiation::with(['lead', 'unit.unitType', 'project', 'creator', 'reviewer', 'booking'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->created_by, fn ($q, $a) => $q->where('created_by', $a))
            ->when($request->search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('client_name', 'like', "%{$s}%")
                    ->orWhere('client_phone', 'like', "%{$s}%");
            }));

        // Role-based visibility scoping for Agent & Master Lead monitoring
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('developer');
        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $query->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        // Auto-expire old negotiations
        Negotiation::where('status', 'draft')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->update(['status' => 'expired']);

        $negotiations = $query->latest()->paginate(20)->withQueryString();

        // Stats scoping
        $statsBase = Negotiation::query();
        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = $teamUserIds ?? User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $statsBase->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $statsBase->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        $stats = [
            'total' => (clone $statsBase)->count(),
            'pending' => (clone $statsBase)->where('status', 'pending')->count(),
            'counter_offer' => (clone $statsBase)->where('status', 'counter_offer')->count(),
            'approved' => (clone $statsBase)->where('status', 'approved')->count(),
            'rejected' => (clone $statsBase)->where('status', 'rejected')->count(),
            'converted' => (clone $statsBase)->whereNotNull('booking_id')->count(),
        ];

        $units = Unit::whereIn('status', ['available', 'reserved'])
            ->select('id', 'block', 'number', 'floor', 'final_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name', 'unitType:id,name'])
            ->orderBy('number')
            ->get();

        $leads = Lead::select('id', 'name', 'phone', 'email', 'project_id')
            ->orderBy('name')
            ->get();

        return Inertia::render('Negotiations/Index', [
            'negotiations' => $negotiations,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project_id', 'created_by', 'search']),
            'projects' => Project::select('id', 'name')->get(),
            'agents' => User::select('id', 'name')->whereIn('agent_type', ['inhouse', 'freelance', 'master_lead'])->orWhereHas('roles', fn ($q) => $q->whereIn('name', ['sales_agent', 'master_lead', 'broker']))->get(),
            'units' => $units,
            'leads' => $leads,
        ]);
    }

    /**
     * CRM Internal: Create a new negotiation (generate link)
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'nullable|exists:leads,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
        ]);

        $unit = Unit::with('project')->findOrFail($request->unit_id);

        $negotiation = Negotiation::create([
            'unit_id' => $unit->id,
            'project_id' => $unit->project_id,
            'lead_id' => $request->lead_id,
            'created_by' => auth()->id(),
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'client_email' => $request->client_email,
            'unit_listed_price' => $unit->final_price ?? $unit->price ?? 0,
            'status' => 'draft',
        ]);

        // Log activity if linked to a lead
        if ($negotiation->lead_id) {
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "📋 Form Negosiasi dibuat untuk unit {$unit->code}. Link: {$negotiation->getPublicUrl()}",
            ]);
        }

        return back()
            ->with('negotiation_link', $negotiation->getPublicUrl())
            ->with('negotiation_token', $negotiation->token)
            ->with('success', 'Form negosiasi berhasil dibuat!');
    }

    /**
     * CRM Internal: Show negotiation detail
     */
    public function show(Negotiation $negotiation)
    {
        $negotiation->load(['lead', 'unit.unitType', 'unit.project', 'project', 'creator', 'reviewer', 'booking']);

        return Inertia::render('Negotiations/Show', [
            'negotiation' => $negotiation,
        ]);
    }

    /**
     * CRM Internal: Review negotiation (approve / counter / reject)
     */
    public function review(Request $request, Negotiation $negotiation)
    {
        $request->validate([
            'action' => 'required|in:approve,counter,reject',
            'counter_price' => 'required_if:action,counter|nullable|numeric|min:0',
            'counter_notes' => 'nullable|string|max:1000',
        ]);

        $action = $request->action;

        if ($action === 'approve') {
            $negotiation->update([
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            // Update lead status
            if ($negotiation->lead_id) {
                Lead::where('id', $negotiation->lead_id)->update(['status' => 'negotiation']);
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => "✅ Negosiasi DISETUJUI untuk unit {$negotiation->unit->code}. Harga: Rp " . number_format($negotiation->offered_price, 0, ',', '.'),
                ]);
            }
        } elseif ($action === 'counter') {
            $negotiation->update([
                'status' => 'counter_offer',
                'counter_price' => $request->counter_price,
                'counter_notes' => $request->counter_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'description' => "🔄 Counter Offer diberikan: Rp " . number_format($request->counter_price, 0, ',', '.') . " untuk unit {$negotiation->unit->code}",
                ]);
            }
        } else {
            $negotiation->update([
                'status' => 'rejected',
                'counter_notes' => $request->counter_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => "❌ Negosiasi DITOLAK untuk unit {$negotiation->unit->code}." . ($request->counter_notes ? " Alasan: {$request->counter_notes}" : ''),
                ]);
            }
        }

        return back()->with('success', match ($action) {
            'approve' => 'Negosiasi disetujui!',
            'counter' => 'Counter offer berhasil dikirim!',
            'reject' => 'Negosiasi ditolak.',
        });
    }

    /**
     * CRM Internal: Convert approved negotiation to booking
     */
    public function convertToBooking(Negotiation $negotiation)
    {
        if ($negotiation->status !== 'approved' && $negotiation->client_response !== 'accepted') {
            return back()->with('error', 'Hanya negosiasi yang disetujui yang bisa dikonversi ke booking.');
        }

        // Redirect to booking create with pre-filled data
        $params = [
            'unit_id' => $negotiation->unit_id,
            'lead_id' => $negotiation->lead_id,
            'negotiation_id' => $negotiation->id,
            'final_price' => $negotiation->status === 'approved' ? $negotiation->offered_price : $negotiation->counter_price,
            'payment_scheme' => $negotiation->payment_scheme ?? 'kpr',
            'dp_amount' => $negotiation->dp_amount,
        ];

        return redirect()->route('bookings.create', $params);
    }

    // ===================================================================
    // PUBLIC ROUTES (No Auth)
    // ===================================================================

    /**
     * Public: Show negotiation form for client
     */
    public function publicForm($token)
    {
        $negotiation = Negotiation::with(['unit.unitType', 'unit.project', 'project', 'creator.brokerCompany'])
            ->where('token', $token)
            ->firstOrFail();

        // Check if expired
        if ($negotiation->isExpired() && $negotiation->status === 'draft') {
            $negotiation->update(['status' => 'expired']);
        }

        $settings = [];
        $settingsRaw = \App\Models\Setting::all();
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = \App\Models\Setting::get($s->key);
        }

        return Inertia::render('Public/NegotiationForm', [
            'negotiation' => $negotiation,
            'settings' => [
                'company_name' => $settings['company_name'] ?? 'Homi Developer',
                'company_logo' => $settings['company_logo'] ?? null,
            ],
        ]);
    }

    /**
     * Public: Client submits their negotiation
     */
    public function publicSubmit(Request $request, $token)
    {
        $negotiation = Negotiation::where('token', $token)->firstOrFail();

        if ($negotiation->isExpired()) {
            $negotiation->update(['status' => 'expired']);
            return back()->with('error', 'Form negosiasi ini sudah kedaluwarsa.');
        }

        if (!in_array($negotiation->status, ['draft', 'counter_offer'])) {
            return back()->with('error', 'Form negosiasi ini sudah tidak bisa diubah.');
        }

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'offered_price' => 'required|numeric|min:1',
            'payment_scheme' => 'required|in:cash_keras,cash_bertahap,kpr',
            'dp_amount' => 'nullable|numeric|min:0',
            'installment_months' => 'nullable|integer|min:1|max:360',
            'special_requests' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:1000',
        ]);

        $negotiation->update(array_merge($validated, [
            'status' => 'pending',
        ]));

        // Auto-link to existing lead if phone matches
        if (!$negotiation->lead_id) {
            $existingLead = Lead::where('phone', $validated['client_phone'])->first();
            if ($existingLead) {
                $negotiation->update(['lead_id' => $existingLead->id]);
            }
        }

        // Log activity
        if ($negotiation->lead_id) {
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => $negotiation->created_by,
                'type' => 'note',
                'description' => "📋 Client mengajukan negosiasi: Rp " . number_format($validated['offered_price'], 0, ',', '.') . " untuk unit {$negotiation->unit->code} (Skema: {$validated['payment_scheme']})",
            ]);

            // Update lead status to negotiation
            Lead::where('id', $negotiation->lead_id)->update(['status' => 'negotiation']);
        }

        return back()->with('success', 'Pengajuan negosiasi berhasil dikirim! Kami akan meninjau dalam 1x24 jam.');
    }

    /**
     * Public: Client responds to counter offer
     */
    public function publicCounterResponse(Request $request, $token)
    {
        $negotiation = Negotiation::where('token', $token)->firstOrFail();

        if ($negotiation->status !== 'counter_offer') {
            return back()->with('error', 'Tidak ada counter offer yang menunggu respons.');
        }

        $request->validate([
            'response' => 'required|in:accepted,rejected,revised',
            'offered_price' => 'required_if:response,revised|nullable|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($request->response === 'accepted') {
            $negotiation->update([
                'client_response' => 'accepted',
                'client_response_at' => now(),
                'offered_price' => $negotiation->counter_price, // Accept counter price
                'status' => 'approved',
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'status_change',
                    'description' => "✅ Client MENERIMA counter offer: Rp " . number_format($negotiation->counter_price, 0, ',', '.'),
                ]);
            }
        } elseif ($request->response === 'revised') {
            $negotiation->update([
                'client_response' => 'revised',
                'client_response_at' => now(),
                'offered_price' => $request->offered_price,
                'notes' => $request->notes,
                'status' => 'pending', // Back to pending for re-review
                'counter_price' => null,
                'counter_notes' => null,
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'note',
                    'description' => "🔄 Client mengajukan harga revisi: Rp " . number_format($request->offered_price, 0, ',', '.'),
                ]);
            }
        } else {
            $negotiation->update([
                'client_response' => 'rejected',
                'client_response_at' => now(),
                'status' => 'rejected',
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'status_change',
                    'description' => "❌ Client MENOLAK counter offer.",
                ]);
            }
        }

        return back()->with('success', match ($request->response) {
            'accepted' => 'Terima kasih! Anda telah menerima penawaran kami.',
            'revised' => 'Harga revisi Anda telah dikirim untuk ditinjau kembali.',
            'rejected' => 'Negosiasi ditutup. Terima kasih atas minat Anda.',
        });
    }
}
