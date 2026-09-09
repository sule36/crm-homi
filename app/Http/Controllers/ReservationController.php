<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Unit;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Negotiation;
use App\Models\Project;
use App\Models\User;
use App\Models\Setting;
use App\Models\AuditLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ReservationController extends Controller
{
    /**
     * Helper to load app settings
     */
    private function getSettings()
    {
        $settingsRaw = Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = Setting::get($s->key);
        }
        return $settings;
    }

    /**
     * List all reservations
     */
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('reservations')) {
            return Inertia::render('Reservations/Index', [
                'reservations' => ['data' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'last_page' => 1, 'links' => []],
                'stats' => ['total' => 0, 'active' => 0, 'converted' => 0, 'refunded' => 0, 'total_amount' => 0, 'total_refunded' => 0],
                'filters' => $request->only(['status', 'project_id', 'search']),
                'projects' => Project::select('id', 'name')->get(),
            ]);
        }

        $user = auth()->user();

        $query = Reservation::with(['project', 'unit.unitType', 'lead', 'creator', 'agentCoordinator', 'booking'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('reservation_number', 'like', "%{$s}%")
                    ->orWhere('client_name', 'like', "%{$s}%")
                    ->orWhere('client_phone', 'like', "%{$s}%")
                    ->orWhereHas('unit', fn ($u) => $u->where('number', 'like', "%{$s}%")->orWhere('block', 'like', "%{$s}%"));
            }));

        // Role-based visibility scoping
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('developer');
        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $query->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereIn('agent_coordinator_id', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhere('agent_coordinator_id', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        $reservations = $query->latest()->paginate(20)->withQueryString();

        $statsBase = Reservation::query();
        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = $teamUserIds ?? User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $statsBase->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereIn('agent_coordinator_id', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $statsBase->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhere('agent_coordinator_id', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        $stats = [
            'total' => (clone $statsBase)->count(),
            'active' => (clone $statsBase)->where('status', 'active')->count(),
            'converted' => (clone $statsBase)->where('status', 'converted')->count(),
            'refunded' => (clone $statsBase)->where('status', 'refunded')->count(),
            'total_amount' => (clone $statsBase)->where('status', 'active')->sum('amount'),
            'total_refunded' => (clone $statsBase)->where('status', 'refunded')->sum('refund_amount'),
        ];

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project_id', 'search']),
            'projects' => Project::select('id', 'name')->get(),
        ]);
    }

    /**
     * Show form to create reservation
     */
    public function create(Request $request)
    {
        $selectedUnitId = $request->query('unit_id');
        $selectedLeadId = $request->query('lead_id');
        $selectedNegoId = $request->query('negotiation_id');

        $units = Unit::where('status', '!=', 'sold')
        ->with(['project:id,name', 'unitType:id,name,current_price'])
        ->orderBy('block')
        ->orderByRaw('CAST(number AS UNSIGNED) ASC')
        ->get();

        $leads = Lead::select('id', 'name', 'phone', 'email', 'identity_number', 'assigned_to')
            ->with(['assignedTo:id,name'])
            ->orderBy('name')
            ->get();

        $coordinators = User::select('id', 'name', 'agent_type')
            ->whereIn('agent_type', ['master_lead', 'inhouse', 'freelance'])
            ->orWhereHas('roles', fn ($q) => $q->whereIn('name', ['super_admin', 'admin', 'developer', 'master_lead', 'sales_agent']))
            ->get();

        $selectedNego = $selectedNegoId ? Negotiation::with(['unit', 'lead'])->find($selectedNegoId) : null;

        return Inertia::render('Reservations/Create', [
            'units' => $units,
            'leads' => $leads,
            'coordinators' => $coordinators,
            'preselectedUnitId' => $selectedUnitId,
            'preselectedLeadId' => $selectedLeadId,
            'preselectedNego' => $selectedNego,
        ]);
    }

    /**
     * Store new reservation
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'nullable|exists:leads,id',
            'negotiation_id' => 'nullable|exists:negotiations,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'client_nik' => 'nullable|string|max:30',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'payment_proof' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
            'company_name' => 'nullable|string|max:255',
            'receipt_title' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'terms_text' => 'nullable|string|max:2000',
            'policy_title' => 'nullable|string|max:255',
            'policy_text' => 'nullable|string|max:2000',
            'custom_overrides' => 'nullable|array',
            'agent_coordinator_id' => 'nullable|exists:users,id',
            'agent_coordinator_name' => 'nullable|string|max:255',
            'agent_coordinator_title' => 'nullable|string|max:255',
            'expires_days' => 'nullable|integer|min:1|max:30',
            'notes' => 'nullable|string|max:1000',
        ]);

        $unit = Unit::with('project')->findOrFail($validated['unit_id']);

        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('reservation_proofs', 'public');
        }

        $coordUser = !empty($validated['agent_coordinator_id'])
            ? User::find($validated['agent_coordinator_id'])
            : auth()->user();

        $coordName = !empty($validated['agent_coordinator_name'])
            ? $validated['agent_coordinator_name']
            : ($coordUser ? $coordUser->name : 'Agent Coordinator');

        $coordTitle = !empty($validated['agent_coordinator_title'])
            ? $validated['agent_coordinator_title']
            : ($coordUser && $coordUser->agent_type === 'master_lead'
                ? 'Master Lead / Agent Coordinator'
                : ($coordUser ? 'Sales Coordinator' : 'Coordinator Representative'));

        $companyName = !empty($validated['company_name'])
            ? $validated['company_name']
            : null;

        $expiresAt = now()->addDays($validated['expires_days'] ?? 7);

        $reservation = Reservation::create([
            'project_id' => $unit->project_id,
            'unit_id' => $unit->id,
            'lead_id' => $validated['lead_id'] ?? null,
            'negotiation_id' => $validated['negotiation_id'] ?? null,
            'created_by' => auth()->id(),
            'client_name' => $validated['client_name'],
            'client_phone' => $validated['client_phone'],
            'client_email' => $validated['client_email'] ?? null,
            'client_nik' => $validated['client_nik'] ?? null,
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'payment_proof' => $proofPath,
            'status' => 'active',
            'refundable_policy' => '100% Refundable (Garansi Pengembalian Utuh)',
            'company_name' => $companyName,
            'receipt_title' => $validated['receipt_title'] ?? null,
            'city' => $validated['city'] ?? null,
            'terms_text' => $validated['terms_text'] ?? null,
            'policy_title' => $validated['policy_title'] ?? null,
            'policy_text' => $validated['policy_text'] ?? null,
            'custom_overrides' => $validated['custom_overrides'] ?? null,
            'agent_coordinator_id' => $coordUser?->id,
            'agent_coordinator_name' => $coordName,
            'agent_coordinator_title' => $coordTitle,
            'expires_at' => $expiresAt,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Lock unit status to 'reserved'
        $unit->update([
            'status' => 'reserved',
            'held_by' => auth()->id(),
            'held_until' => $expiresAt,
        ]);

        // If linked to lead, record activity and set status to 'reservation'
        if ($reservation->lead_id) {
            $lead = Lead::find($reservation->lead_id);
            if ($lead) {
                $lead->update(['status' => 'reservation']);
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'description' => "🔖 Reservasi Unit {$unit->label} dibuat (No: {$reservation->reservation_number}). Nominal: Rp " . number_format($reservation->amount, 0, ',', '.') . " (Garansi 100% Refundable).",
                ]);
            }
        }

        AuditLog::record('reservation_created', $reservation, null, $validated);

        return redirect()->route('reservations.show', $reservation->id)
            ->with('success', "Reservasi unit berhasil dibuat dengan No. {$reservation->reservation_number}.");
    }

    /**
     * Show reservation detail
     */
    public function show(Reservation $reservation)
    {
        $reservation->load(['project', 'unit.unitType', 'lead', 'negotiation', 'creator', 'agentCoordinator', 'refunder', 'booking']);
        $settings = $this->getSettings();

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'settings' => $settings,
        ]);
    }

    /**
     * Update reservation data & receipt authentication (PT & Signatures & Template Overrides)
     */
    public function update(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'client_nik' => 'nullable|string|max:30',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'receipt_title' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'terms_text' => 'nullable|string|max:2000',
            'policy_title' => 'nullable|string|max:255',
            'policy_text' => 'nullable|string|max:2000',
            'custom_overrides' => 'nullable|array',
            'agent_coordinator_id' => 'nullable|exists:users,id',
            'agent_coordinator_name' => 'nullable|string|max:255',
            'agent_coordinator_title' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $reservation->update($validated);

        return back()->with('success', 'Kwitansi reservasi berhasil diperbarui.');
    }

    /**
     * Process 100% Refund for cancelled reservation
     */
    public function processRefund(Request $request, Reservation $reservation)
    {
        if ($reservation->status === 'refunded') {
            return back()->with('error', 'Reservasi ini sudah diproses refund sebelumnya.');
        }

        $validated = $request->validate([
            'refund_bank_name' => 'required|string|max:100',
            'refund_account_number' => 'required|string|max:50',
            'refund_account_name' => 'required|string|max:100',
            'refund_reason' => 'required|string|max:1000',
            'refund_proof' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:5120',
        ]);

        $proofPath = null;
        if ($request->hasFile('refund_proof')) {
            $proofPath = $request->file('refund_proof')->store('refund_proofs', 'public');
        }

        $oldStatus = $reservation->status;

        $reservation->update([
            'status' => 'refunded',
            'refund_amount' => $reservation->amount, // 100% Refundable
            'refund_date' => now(),
            'refund_bank_name' => $validated['refund_bank_name'],
            'refund_account_number' => $validated['refund_account_number'],
            'refund_account_name' => $validated['refund_account_name'],
            'refund_reason' => $validated['refund_reason'],
            'refund_proof' => $proofPath,
            'refunded_by' => auth()->id(),
        ]);

        // Release Unit back to 'available'
        if ($reservation->unit) {
            $reservation->unit->update([
                'status' => 'available',
                'held_by' => null,
                'held_until' => null,
            ]);
        }

        // Record activity if linked to lead
        if ($reservation->lead_id) {
            LeadActivity::create([
                'lead_id' => $reservation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "🔄 Reservasi No: {$reservation->reservation_number} DIBATALKAN & DI-REFUND 100% (Rp " . number_format($reservation->amount, 0, ',', '.') . "). Unit {$reservation->unit?->label} telah kembali siap dipesan (available).",
            ]);
        }

        AuditLog::record('reservation_refunded', $reservation, ['status' => $oldStatus], $validated);

        return back()->with('success', "Refund 100% sebesar Rp " . number_format($reservation->amount, 0, ',', '.') . " berhasil diproses.");
    }

    /**
     * Redirect to Booking creation with reservation credit pre-filled
     */
    public function convertToBooking(Reservation $reservation)
    {
        if ($reservation->status === 'refunded' || $reservation->status === 'cancelled') {
            return back()->with('error', 'Reservasi yang sudah dibatalkan/direfund tidak dapat dikonversi.');
        }

        return redirect()->route('bookings.create', [
            'reservation_id' => $reservation->id,
            'unit_id' => $reservation->unit_id,
            'lead_id' => $reservation->lead_id,
            'reserved_amount' => $reservation->amount,
        ]);
    }

    /**
     * Stream PDF Kwitansi Reservasi (TTD Agent Koordinator)
     */
    public function streamReceiptPdf(Reservation $reservation)
    {
        $reservation->load(['project', 'unit.unitType', 'lead', 'creator', 'agentCoordinator']);
        $settings = $this->getSettings();

        if (request()->has('html') || request()->query('view') === 'html') {
            return view('pdf.reservation_receipt', compact('reservation', 'settings'));
        }

        $pdf = Pdf::loadView('pdf.reservation_receipt', compact('reservation', 'settings'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [public_path(), storage_path()]);

        $safeNumber = str_replace(['/', '\\', ' '], '_', $reservation->reservation_number ?: 'RES-' . $reservation->id);

        if (request()->has('download')) {
            return $pdf->download("Kwitansi_Reservasi_{$safeNumber}.pdf");
        }

        return $pdf->stream("Kwitansi_Reservasi_{$safeNumber}.pdf");
    }

    /**
     * Delete reservation (soft delete)
     */
    public function destroy(Reservation $reservation)
    {
        if ($reservation->status === 'active' && $reservation->unit) {
            $reservation->unit->update([
                'status' => 'available',
                'held_by' => null,
                'held_until' => null,
            ]);
        }

        $reservation->delete();

        return redirect()->route('reservations.index')->with('success', 'Data reservasi berhasil dihapus.');
    }
}
