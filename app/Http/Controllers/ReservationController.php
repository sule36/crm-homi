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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $settings['standard_booking_fee'] = Setting::getStandardBookingFee();

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

        $unreservedLeadsQuery = Lead::where('status', 'reservation')
            ->whereDoesntHave('reservations', fn ($q) => $q->whereIn('status', ['active', 'converted']))
            ->with(['project', 'assignedTo']);

        if (!$isAdmin) {
            if ($isMasterLead) {
                $unreservedLeadsQuery->whereIn('assigned_to', $teamUserIds);
            } else {
                $unreservedLeadsQuery->where('assigned_to', $user->id);
            }
        }

        $unreservedLeads = $unreservedLeadsQuery->latest()->take(10)->get();

        return Inertia::render('Reservations/Index', [
            'reservations' => $reservations,
            'unreservedLeads' => $unreservedLeads,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project_id', 'search']),
            'projects' => Project::select('id', 'name')->get(),
        ]);
    }

    private function sanitizeReservationInput(Request $request): void
    {
        foreach (['agent_coordinator_id', 'lead_id', 'negotiation_id', 'unit_id'] as $nullableKey) {
            if ($request->has($nullableKey) && empty($request->input($nullableKey))) {
                $request->merge([$nullableKey => null]);
            }
        }

        if ($request->has('amount') && is_string($request->input('amount'))) {
            $cleaned = preg_replace('/[^0-9]/', '', $request->input('amount'));
            if ($cleaned !== '') {
                $request->merge(['amount' => (int) $cleaned]);
            }
        }
    }

    private function ensureFlexibleStatusColumns(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        try {
            $dbName = DB::connection()->getDatabaseName();
            foreach (['units' => 'available', 'leads' => 'new'] as $table => $default) {
                $col = DB::selectOne(
                    "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = 'status'",
                    [$dbName, $table]
                );
                if ($col && strtolower((string) $col->DATA_TYPE) === 'enum') {
                    DB::statement("ALTER TABLE {$table} MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT '{$default}'");
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Skipping pre-transaction status column check: ' . $e->getMessage());
        }
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
            ->orderByUnitNumber()
            ->get();

        $leads = Lead::select('id', 'name', 'phone', 'email', 'identity_number', 'assigned_to', 'inhouse_pic_id')
            ->with(['assignedTo:id,name', 'inhousePic:id,name'])
            ->orderBy('name')
            ->get();

        $coordinators = User::select('id', 'name', 'agent_type')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $selectedNego = $selectedNegoId ? Negotiation::with(['unit', 'lead.assignedTo', 'lead.inhousePic'])->find($selectedNegoId) : null;

        return Inertia::render('Reservations/Create', [
            'units' => $units,
            'leads' => $leads,
            'coordinators' => $coordinators,
            'preselectedUnitId' => $selectedUnitId,
            'preselectedLeadId' => $selectedLeadId,
            'preselectedNego' => $selectedNego,
            'defaultCompanyName' => Setting::get('company_name'),
            'standardBookingFee' => Setting::getStandardBookingFee(),
        ]);
    }

    /**
     * Store new reservation
     */
    public function store(Request $request)
    {
        $this->sanitizeReservationInput($request);

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

        // Ensure MySQL ENUM columns (if any legacy schema remains) are widened OUTSIDE DB::beginTransaction()
        // because DDL statements cause an implicit commit in MySQL.
        $this->ensureFlexibleStatusColumns();

        try {
            DB::beginTransaction();

            $unit = Unit::with('project')->findOrFail($validated['unit_id']);
            if (in_array($unit->status, ['sold', 'booked'], true)) {
                DB::rollBack();
                return back()->withInput()->with('error', "Unit {$unit->label} sudah berstatus {$unit->status} dan tidak dapat direservasi.");
            }
            $lead = !empty($validated['lead_id'])
                ? Lead::with(['assignedTo', 'inhousePic'])->find($validated['lead_id'])
                : null;

            $proofPath = null;
            if ($request->hasFile('payment_proof')) {
                $proofPath = $request->file('payment_proof')->store('reservation_proofs', 'public');
            }

            $coordUser = !empty($validated['agent_coordinator_id'])
                ? User::find($validated['agent_coordinator_id'])
                : ($lead?->assignedTo ?? $lead?->inhousePic ?? auth()->user());

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

            $expiresAt = now()->addDays((int) ($validated['expires_days'] ?? 7));

            $reservationData = [
                'company_id' => auth()->user()?->company_id ?: ($unit->company_id ?: $unit->project?->company_id),
                'reservation_number' => Reservation::generateReservationNumber($unit->project_id),
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
            ];

            // Safe filter against actual database schema to prevent crashes if migrations aren't executed yet
            $safeData = [];
            foreach ($reservationData as $column => $value) {
                if (Schema::hasColumn('reservations', $column)) {
                    $safeData[$column] = $value;
                }
            }

            $reservation = Reservation::create($safeData);

            // Lock unit status to 'reserved' (with safe fallback to 'hold' if status constraint rejects 'reserved')
            try {
                $unit->update([
                    'status' => 'reserved',
                    'held_by' => auth()->id(),
                    'held_until' => $expiresAt,
                ]);
            } catch (\Throwable $ue) {
                Log::warning("Direct unit update to reserved failed, falling back to hold: " . $ue->getMessage());
                $unit->update([
                    'status' => 'hold',
                    'held_by' => auth()->id(),
                    'held_until' => $expiresAt,
                ]);
            }

            // If linked to lead, record activity and set status to 'reservation' (with safe fallback to 'negotiation')
            if ($reservation->lead_id) {
                $lead = Lead::find($reservation->lead_id);
                if ($lead) {
                    try {
                        $lead->update(['status' => 'reservation']);
                    } catch (\Throwable $le) {
                        Log::warning("Direct lead update to reservation failed, falling back to negotiation: " . $le->getMessage());
                        $lead->update(['status' => 'negotiation']);
                    }

                    try {
                        $unitLabel = $unit->label ?? ($unit->block . '-' . $unit->number);
                        LeadActivity::create([
                            'lead_id' => $lead->id,
                            'user_id' => auth()->id(),
                            'type' => 'note',
                            'description' => "🔖 Reservasi Unit {$unitLabel} dibuat (No: {$reservation->reservation_number}). Nominal: Rp " . number_format($reservation->amount, 0, ',', '.') . " (Garansi 100% Refundable).",
                        ]);
                    } catch (\Throwable $ae) {
                        Log::warning("Lead activity record skipped: " . $ae->getMessage());
                    }
                }
            }

            AuditLog::record('reservation_created', $reservation, null, $validated);

            DB::commit();

            if ($request->input('redirect_to') === 'lead' && $reservation->lead_id) {
                return redirect()->route('leads.show', $reservation->lead_id)
                    ->with('success', "Reservasi unit {$unit->label} berhasil dibuat (No. {$reservation->reservation_number}). Unit berhasil di-hold!");
            }

            return redirect()->route('reservations.show', $reservation->id)
                ->with('success', "Reservasi unit berhasil dibuat dengan No. {$reservation->reservation_number}.");
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error("Failed to store reservation: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'Gagal membuat reservasi: ' . $e->getMessage());
        }
    }

    /**
     * Show reservation detail
     */
    public function show(Reservation $reservation)
    {
        $reservation->load(['project', 'unit.unitType', 'lead.assignedTo', 'lead.inhousePic', 'negotiation', 'creator', 'agentCoordinator', 'refunder', 'booking']);
        $settings = $this->getSettings();

        $units = Unit::where('status', '!=', 'sold')
            ->when($reservation->project_id, fn ($q) => $q->where('project_id', $reservation->project_id))
            ->select('id', 'block', 'number', 'floor', 'final_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name', 'unitType:id,name'])
            ->orderByUnitNumber()
            ->get();

        $coordinators = User::select('id', 'name', 'agent_type')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return Inertia::render('Reservations/Show', [
            'reservation' => $reservation,
            'settings' => $settings,
            'units' => $units,
            'coordinators' => $coordinators,
            'standardBookingFee' => Setting::getStandardBookingFee(),
        ]);
    }

    /**
     * Change reserved unit (free old unit, lock new unit)
     */
    public function changeUnit(Request $request, Reservation $reservation)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($reservation->status !== 'active') {
            return back()->with('error', 'Hanya reservasi berstatus aktif yang dapat diubah unitnya.');
        }

        if ($reservation->unit_id == $validated['unit_id']) {
            return back()->with('info', 'Unit yang dipilih sama dengan unit reservasi saat ini.');
        }

        $newUnit = Unit::with('project')->findOrFail($validated['unit_id']);
        if ($newUnit->status !== 'available') {
            return back()->with('error', "Unit {$newUnit->label} saat ini tidak berstatus Available (Status: {$newUnit->status}).");
        }

        $oldUnit = Unit::find($reservation->unit_id);

        // 1. Release old unit
        if ($oldUnit) {
            $oldUnit->update([
                'status' => 'available',
                'held_by' => null,
                'held_until' => null,
            ]);
        }

        // 2. Lock new unit
        $newUnit->update([
            'status' => 'reserved',
            'held_by' => $reservation->created_by ?? auth()->id(),
            'held_until' => $reservation->expires_at ?? now()->addDays(7),
        ]);

        // 3. Update reservation and clear stale unit-specific custom overrides
        $overrides = is_array($reservation->custom_overrides) ? $reservation->custom_overrides : [];
        foreach (['unit_code', 'unit_type_name', 'spec_text', 'project_name'] as $staleKey) {
            unset($overrides[$staleKey]);
        }

        $reservation->update([
            'unit_id' => $newUnit->id,
            'project_id' => $newUnit->project_id,
            'custom_overrides' => !empty($overrides) ? $overrides : null,
        ]);

        // 4. Record Lead Activity
        if ($reservation->lead_id) {
            $reasonText = !empty($validated['reason']) ? " Alasan: {$validated['reason']}" : "";
            LeadActivity::create([
                'lead_id' => $reservation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "🔄 Konsumen berpindah unit reservasi dari " . ($oldUnit ? $oldUnit->label : "Unit #{$reservation->unit_id}") . " ke {$newUnit->label}.{$reasonText}",
            ]);
        }

        AuditLog::record('reservation_unit_changed', $reservation, ['old_unit_id' => $oldUnit?->id], ['new_unit_id' => $newUnit->id]);

        return back()->with('success', "Unit reservasi berhasil diubah ke {$newUnit->label}!");
    }

    /**
     * Update reservation data & receipt authentication (PT & Signatures & Template Overrides)
     */
    public function update(Request $request, Reservation $reservation)
    {
        $this->sanitizeReservationInput($request);

        $validated = $request->validate([
            'unit_id' => 'nullable|exists:units,id',
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

        // If unit_id is being changed in update
        if (!empty($validated['unit_id']) && $validated['unit_id'] != $reservation->unit_id) {
            $newUnit = Unit::with('project')->findOrFail($validated['unit_id']);
            if ($newUnit->status === 'available') {
                $oldUnit = Unit::find($reservation->unit_id);
                if ($oldUnit) {
                    $oldUnit->update(['status' => 'available', 'held_by' => null, 'held_until' => null]);
                }
                $newUnit->update([
                    'status' => 'reserved',
                    'held_by' => $reservation->created_by ?? auth()->id(),
                    'held_until' => $reservation->expires_at ?? now()->addDays(7),
                ]);
                $validated['project_id'] = $newUnit->project_id;

                if ($reservation->lead_id) {
                    LeadActivity::create([
                        'lead_id' => $reservation->lead_id,
                        'user_id' => auth()->id(),
                        'type' => 'note',
                        'description' => "🔄 Unit Reservasi diubah dari " . ($oldUnit ? $oldUnit->label : "Unit #{$reservation->unit_id}") . " ke {$newUnit->label}.",
                    ]);
                }
            }
        }

        // Safe filter against actual database schema to prevent crashes if migrations aren't executed yet
        $safeData = [];
        foreach ($validated as $column => $value) {
            if (Schema::hasColumn('reservations', $column)) {
                $safeData[$column] = $value;
            }
        }

        $reservation->update($safeData);

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

        // Record activity and restore lead status to negotiation
        if ($reservation->lead_id) {
            $lead = Lead::find($reservation->lead_id);
            if ($lead) {
                if ($lead->status === 'reservation') {
                    $lead->update(['status' => 'negotiation']);
                    $lead->recalculateScore();
                }
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'description' => "🔄 Reservasi No: {$reservation->reservation_number} DIBATALKAN & DI-REFUND 100% (Rp " . number_format($reservation->amount, 0, ',', '.') . "). Unit {$reservation->unit?->label} telah kembali siap dipesan (available).",
                ]);
            }
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
        $reservation->load(['project', 'unit.unitType', 'lead.assignedTo', 'lead.inhousePic', 'creator', 'agentCoordinator']);
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
        if ($reservation->status === 'active') {
            if ($reservation->unit) {
                $reservation->unit->update([
                    'status' => 'available',
                    'held_by' => null,
                    'held_until' => null,
                ]);
            }
            if ($reservation->lead && $reservation->lead->status === 'reservation') {
                $reservation->lead->update(['status' => 'negotiation']);
                $reservation->lead->recalculateScore();
            }
        }

        $reservation->delete();

        return redirect()->route('reservations.index')->with('success', 'Data reservasi berhasil dihapus.');
    }
}
