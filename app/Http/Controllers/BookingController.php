<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\Lead;
use App\Models\Reservation;
use App\Models\Negotiation;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with(['unit.project', 'lead', 'bookedBy', 'inhousePic'])
            ->when($request->search, function ($q, $s) {
                $q->whereHas('lead', fn($query) => $query->where('name', 'like', "%{$s}%"))
                  ->orWhere('spk_number', 'like', "%{$s}%");
            })
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request)
    {
        $reservation = $request->reservation_id ? Reservation::with(['unit', 'lead'])->find($request->reservation_id) : null;
        $negotiation = $request->negotiation_id 
            ? Negotiation::with(['unit.project', 'unit.unitType', 'lead', 'creator'])->find($request->negotiation_id) 
            : ($reservation?->negotiation_id ? Negotiation::with(['unit.project', 'unit.unitType', 'lead', 'creator'])->find($reservation->negotiation_id) : null);
        $unitId = $request->unit_id ?? $reservation?->unit_id ?? $negotiation?->unit_id;
        $leadId = $request->lead_id ?? $reservation?->lead_id ?? $negotiation?->lead_id;

        // Auto-detect active reservation if launched directly from Negotiation or Lead
        if (!$reservation && ($leadId || $unitId || $negotiation)) {
            $reservation = Reservation::with(['unit', 'lead'])
                ->where('status', 'active')
                ->where(function ($q) use ($leadId, $unitId, $negotiation) {
                    if ($negotiation) {
                        $q->where('negotiation_id', $negotiation->id);
                    }
                    if ($leadId && $unitId) {
                        $q->orWhere(function ($sq) use ($leadId, $unitId) {
                            $sq->where('lead_id', $leadId)->where('unit_id', $unitId);
                        });
                    }
                    if ($leadId) {
                        $q->orWhere('lead_id', $leadId);
                    }
                })
                ->latest()
                ->first();
        }

        // Auto-detect matching negotiation if not explicitly provided
        if (!$negotiation && $leadId && $unitId) {
            $negotiation = Negotiation::with(['unit.project', 'unit.unitType', 'lead', 'creator'])
                ->where('lead_id', $leadId)
                ->where('unit_id', $unitId)
                ->whereIn('status', ['approved', 'counter_offer', 'pending'])
                ->latest()
                ->first();
        }
        if (!$negotiation && $leadId) {
            $negotiation = Negotiation::with(['unit.project', 'unit.unitType', 'lead', 'creator'])
                ->where('lead_id', $leadId)
                ->whereIn('status', ['approved', 'counter_offer', 'pending'])
                ->latest()
                ->first();
        }

        // Available negotiations for this lead or unit to let developer switch/apply deal
        $availableNegotiations = ($leadId || $unitId)
            ? Negotiation::with(['unit.project', 'unit.unitType', 'lead', 'creator'])
                ->where(function($q) use ($leadId, $unitId) {
                    if ($leadId) $q->where('lead_id', $leadId);
                    if ($unitId) $q->orWhere('unit_id', $unitId);
                })
                ->latest()
                ->get()
            : [];

        $reservedAmount = $request->reserved_amount ?? $reservation?->amount ?? null;
        $defaultFreePpn = \App\Models\Setting::get('spr_default_free_ppn', true);
        $defaultFreeLegal = \App\Models\Setting::get('spr_default_free_legal', true);

        return Inertia::render('Bookings/Create', [
            'unit' => $unitId ? Unit::with('project', 'unitType')->find($unitId) : null,
            'lead' => $leadId ? Lead::with(['assignedToUser', 'inhousePic', 'brokerCompany'])->find($leadId) : null,
            'reservation' => $reservation,
            'negotiation' => $negotiation,
            'availableNegotiations' => $availableNegotiations,
            'reservedAmount' => $reservedAmount ? (float)$reservedAmount : null,
            'defaultFreePpn' => (bool)$defaultFreePpn,
            'defaultFreeLegal' => (bool)$defaultFreeLegal,
            'standardBookingFee' => \App\Models\Setting::getStandardBookingFee(),
            'availableUnits' => Unit::selectableForLov($unitId ? (int) $unitId : null)->with('project', 'unitType')->orderByUnitNumber()->get(),
            'leads' => Lead::whereNotIn('status', ['won', 'lost'])
                ->with([
                    'assignedToUser',
                    'inhousePic',
                    'brokerCompany',
                    'reservations' => fn($q) => $q->where('status', 'active')->with(['unit.project', 'unit.unitType'])->latest(),
                    'negotiations' => fn($q) => $q->whereIn('status', ['approved', 'counter_offer', 'pending'])->with(['unit.project', 'unit.unitType', 'lead', 'creator'])->latest(),
                ])
                ->get(),
            'agents' => \App\Models\User::with('brokerCompany')->orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        // 1. Sanitize empty strings and numeric inputs
        if ($request->has('negotiation_id') && empty($request->input('negotiation_id'))) {
            $request->merge(['negotiation_id' => null]);
        }
        if ($request->has('reservation_id') && empty($request->input('reservation_id'))) {
            $request->merge(['reservation_id' => null]);
        }
        foreach (['booking_fee', 'base_price', 'final_price', 'ppn_amount', 'bphtb_amount', 'ajb_bbn_amount', 'other_legal_fees', 'dp_amount'] as $nField) {
            if ($request->has($nField) && is_string($request->input($nField))) {
                $rawVal = trim($request->input($nField));
                if ($rawVal === '') {
                    $request->merge([$nField => 0]);
                } elseif (is_numeric($rawVal)) {
                    $request->merge([$nField => (float)$rawVal]);
                } else {
                    $cleaned = preg_replace('/[^0-9]/', '', $rawVal);
                    $request->merge([$nField => $cleaned !== '' ? (float)$cleaned : 0]);
                }
            }
        }
        if ($request->has('custom_schedules') && is_array($request->input('custom_schedules'))) {
            $normalizedSchedules = [];
            foreach ($request->input('custom_schedules') as $row) {
                if (!is_array($row)) {
                    continue;
                }
                if (isset($row['amount']) && is_string($row['amount'])) {
                    $rawAmt = trim($row['amount']);
                    if ($rawAmt === '') {
                        $row['amount'] = 0;
                    } elseif (is_numeric($rawAmt)) {
                        $row['amount'] = (float)$rawAmt;
                    } else {
                        $cleanedAmt = preg_replace('/[^0-9]/', '', $rawAmt);
                        $row['amount'] = $cleanedAmt !== '' ? (float)$cleanedAmt : 0;
                    }
                }
                $normalizedSchedules[] = $row;
            }
            $request->merge(['custom_schedules' => $normalizedSchedules]);
        }

        $validated = $request->validate([
            'reservation_id' => 'nullable|exists:reservations,id',
            'negotiation_id' => 'nullable|exists:negotiations,id',
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'required|exists:leads,id',
            'booked_by' => 'required|exists:users,id',
            'inhouse_pic_id' => 'nullable|exists:users,id',
            'booking_fee' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'ppn_amount' => 'nullable|numeric|min:0',
            'bphtb_amount' => 'nullable|numeric|min:0',
            'ajb_bbn_amount' => 'nullable|numeric|min:0',
            'other_legal_fees' => 'nullable|numeric|min:0',
            'final_price' => 'required|numeric|min:0',
            'payment_scheme' => 'required|in:cash,cash_installment,kpr',
            'schedule_mode' => 'nullable|in:standard,custom_terms',
            'custom_schedules' => 'nullable|array|max:60',
            'custom_schedules.*.label' => 'required|string|max:255',
            'custom_schedules.*.amount' => 'required|numeric|min:0',
            'custom_schedules.*.due_date' => 'nullable|date',
            'installment_months' => 'nullable|integer|min:0|max:360',
            'dp_amount' => 'nullable|numeric|min:0',
            'dp_installment_months' => 'nullable|integer|min:0|max:60',
            'booking_date' => 'nullable|date',
            'buyer_nik' => 'nullable|string|max:50',
            'buyer_npwp' => 'nullable|string|max:50',
            'buyer_address' => 'nullable|string',
            'buyer_job' => 'nullable|string|max:100',
            'secondary_name' => 'nullable|string|max:255',
            'secondary_nik' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:20',
            'secondary_relationship' => 'nullable|string|max:100',
            'secondary_address' => 'nullable|string',
            'secondary_email' => 'nullable|email|max:255',
            'sig1_title' => 'nullable|string|max:100',
            'sig1_name' => 'nullable|string|max:255',
            'sig2_title' => 'nullable|string|max:100',
            'sig2_name' => 'nullable|string|max:255',
            'sig3_title' => 'nullable|string|max:100',
            'sig3_name' => 'nullable|string|max:255',
            'sig4_title' => 'nullable|string|max:100',
            'sig4_name' => 'nullable|string|max:255',
            'special_bonus_items' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $isCustomTerms = ($validated['payment_scheme'] === 'cash_installment')
            && (($validated['schedule_mode'] ?? 'standard') === 'custom_terms');

        $customSchedules = [];
        if ($isCustomTerms) {
            $customSchedules = array_values(array_filter(
                $validated['custom_schedules'] ?? [],
                fn ($row) => isset($row['label']) && trim((string)$row['label']) !== ''
            ));

            if (empty($customSchedules)) {
                return back()->withErrors([
                    'custom_schedules' => 'Tambahkan minimal 1 termin pembayaran custom untuk skema Cash Bertahap.',
                ])->withInput();
            }

            $taxLegalTotalCheck = (float)($validated['ppn_amount'] ?? 0)
                + (float)($validated['bphtb_amount'] ?? 0)
                + (float)($validated['ajb_bbn_amount'] ?? 0)
                + (float)($validated['other_legal_fees'] ?? 0);
            $basePriceCheck = (float)$validated['base_price'];
            $finalPriceCheck = (float)$validated['final_price'];
            $unitTargetCheck = ($taxLegalTotalCheck > 0 && $finalPriceCheck > $basePriceCheck && $basePriceCheck > 0)
                ? $basePriceCheck
                : ($finalPriceCheck > 0 ? $finalPriceCheck : $basePriceCheck);
            $expectedRemaining = max(0, $unitTargetCheck - (float)$validated['booking_fee']);
            $customSum = array_sum(array_map(fn ($r) => max(0, round((float)($r['amount'] ?? 0))), $customSchedules));

            if (abs($customSum - $expectedRemaining) > 100) {
                return back()->withErrors([
                    'custom_schedules' => 'Total nominal termin custom (Rp ' . number_format($customSum, 0, ',', '.') . ') harus sama dengan sisa harga unit setelah UTJ (Rp ' . number_format($expectedRemaining, 0, ',', '.') . ').',
                ])->withInput();
            }

            $validated['dp_amount'] = $customSum;
            $validated['dp_installment_months'] = count($customSchedules);
            $validated['installment_months'] = 0;
        }

        $preCheckUnit = Unit::findOrFail($validated['unit_id']);
        $unitStatusNorm = strtolower(trim((string) $preCheckUnit->status));
        $hasBlockingBooking = Booking::withoutGlobalScopes()
            ->where('unit_id', $preCheckUnit->id)
            ->whereNull('deleted_at')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('pending', 'approved', 'completed', 'booked', 'sold')")
            ->exists();

        if (in_array($unitStatusNorm, ['sold', 'booked'], true) || $hasBlockingBooking) {
            return back()->withErrors([
                'unit_id' => "Unit {$preCheckUnit->label} sudah berstatus {$preCheckUnit->status} / memiliki booking aktif dan tidak dapat dibooking.",
            ])->withInput();
        }

        // Check if unit is currently held/reserved by an active reservation; only the matching lead/reservation can convert it
        $activeUnitReservation = Reservation::withoutGlobalScopes()
            ->where('unit_id', $preCheckUnit->id)
            ->whereNull('deleted_at')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'")
            ->latest('id')
            ->first();

        if ($activeUnitReservation) {
            $matchesSentRes = !empty($validated['reservation_id']) && (int) $validated['reservation_id'] === (int) $activeUnitReservation->id;
            $matchesLeadRes = (int) $activeUnitReservation->lead_id > 0 && (int) $activeUnitReservation->lead_id === (int) $validated['lead_id'];
            if (!$matchesSentRes && !$matchesLeadRes) {
                return back()->withErrors([
                    'unit_id' => "Unit {$preCheckUnit->label} sedang dalam status reservasi aktif oleh konsumen lain.",
                ])->withInput();
            }
            if (empty($validated['reservation_id'])) {
                $validated['reservation_id'] = $activeUnitReservation->id;
            }
        } elseif (in_array($unitStatusNorm, ['reserved', 'hold'], true)) {
            $leadOwnsPendingNego = !empty($validated['negotiation_id']) && Negotiation::withoutGlobalScopes()
                ->where('id', $validated['negotiation_id'])
                ->where('unit_id', $preCheckUnit->id)
                ->exists();
            if (!$leadOwnsPendingNego) {
                return back()->withErrors([
                    'unit_id' => "Unit {$preCheckUnit->label} saat ini sedang di-hold / tidak tersedia untuk dibooking.",
                ])->withInput();
            }
        }

        try {
            return DB::transaction(function () use ($validated, $request, $isCustomTerms, $customSchedules) {
                $unit = Unit::findOrFail($validated['unit_id']);
                $agent = \App\Models\User::findOrFail($validated['booked_by']);
                $agent->load('brokerCompany');
                $rate = $agent->effective_commission_rate;
                $baseCommission = $validated['final_price'] * ($rate / 100);
                $promoBonus = (float)($agent->custom_bonus ?? 0);
                $commissionAmount = $baseCommission + $promoBonus;

                $sigConfig = \App\Models\Setting::getSignatureConfig();

                $leadRecord = Lead::find($validated['lead_id']);
                $resolvedCompanyId = $unit->company_id ?? $leadRecord?->company_id ?? $agent->company_id ?? auth()->user()?->company_id;

                // 1. Create Booking safely with schema filtering
                $bookingData = [
                    'company_id' => $resolvedCompanyId,
                    'spk_number' => Booking::generateSpkNumber($unit->project_id),
                    'unit_id' => $validated['unit_id'],
                    'lead_id' => $validated['lead_id'],
                    'project_id' => $unit->project_id,
                    'booked_by' => $validated['booked_by'],
                    'inhouse_pic_id' => $validated['inhouse_pic_id'] ?? $leadRecord?->inhouse_pic_id,
                    'booking_fee' => $validated['booking_fee'],
                    'unit_price' => ($unit->final_price > 0 ? $unit->final_price : ($unit->unitType?->current_price > 0 ? $unit->unitType->current_price : $validated['base_price'])),
                    'base_price' => $validated['base_price'],
                    'ppn_amount' => $validated['ppn_amount'] ?? 0,
                    'bphtb_amount' => $validated['bphtb_amount'] ?? 0,
                    'ajb_bbn_amount' => $validated['ajb_bbn_amount'] ?? 0,
                    'other_legal_fees' => $validated['other_legal_fees'] ?? 0,
                    'final_price' => $validated['final_price'],
                    'payment_scheme' => $validated['payment_scheme'],
                    'installment_months' => array_key_exists('installment_months', $validated) && $validated['installment_months'] !== null ? (int)$validated['installment_months'] : (($validated['dp_amount'] ?? 0) > 0 ? 0 : 12),
                    'dp_amount' => $validated['dp_amount'] ?? 0,
                    'dp_installment_months' => $validated['dp_installment_months'] ?? 0,
                    'booking_date' => $validated['booking_date'] ?? now()->format('Y-m-d'),
                    'buyer_nik' => $validated['buyer_nik'] ?? null,
                    'buyer_npwp' => $validated['buyer_npwp'] ?? null,
                    'buyer_address' => $validated['buyer_address'] ?? null,
                    'buyer_job' => $validated['buyer_job'] ?? null,
                    'secondary_name' => $validated['secondary_name'] ?? null,
                    'secondary_nik' => $validated['secondary_nik'] ?? null,
                    'secondary_phone' => $validated['secondary_phone'] ?? null,
                    'secondary_relationship' => $validated['secondary_relationship'] ?? null,
                    'secondary_address' => $validated['secondary_address'] ?? null,
                    'secondary_email' => $validated['secondary_email'] ?? null,
                    'sig1_title' => $validated['sig1_title'] ?? ($sigConfig['sig1_title'] ?? null),
                    'sig1_name' => $validated['sig1_name'] ?? ($sigConfig['sig1_name'] ?: null),
                    'sig2_title' => $validated['sig2_title'] ?? ($sigConfig['sig2_title'] ?? null),
                    'sig2_name' => $validated['sig2_name'] ?? ($sigConfig['sig2_name'] ?: null),
                    'sig3_title' => $validated['sig3_title'] ?? ($sigConfig['sig3_title'] ?? 'SALES'),
                    'sig3_name' => $validated['sig3_name'] ?? ($agent->name ?: null),
                    'sig4_title' => $validated['sig4_title'] ?? null,
                    'sig4_name' => $validated['sig4_name'] ?? null,
                    'special_bonus_items' => $validated['special_bonus_items'] ?? null,
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                    'commission_amount' => $commissionAmount,
                ];

                $safeData = [];
                foreach ($bookingData as $col => $val) {
                    if (Schema::hasColumn('bookings', $col)) {
                        $safeData[$col] = $val;
                    }
                }

                $booking = Booking::create($safeData);

                // Update linked reservation if present
                if (!empty($validated['reservation_id'])) {
                    $reservation = Reservation::find($validated['reservation_id']);
                    if ($reservation) {
                        $resUpdate = ['status' => 'converted'];
                        if (Schema::hasColumn('reservations', 'booking_id')) {
                            $resUpdate['booking_id'] = $booking->id;
                        }
                        try {
                            $reservation->update($resUpdate);
                        } catch (\Throwable $re) {
                            Log::warning("Reservation status update failed: " . $re->getMessage());
                        }
                    }
                }

                // Update linked negotiation if present
                if (!empty($validated['negotiation_id'])) {
                    $negotiation = Negotiation::find($validated['negotiation_id']);
                    if ($negotiation) {
                        $negoUpdate = ['status' => 'approved'];
                        if (Schema::hasColumn('negotiations', 'booking_id')) {
                            $negoUpdate['booking_id'] = $booking->id;
                        }
                        try {
                            $negotiation->update($negoUpdate);
                        } catch (\Throwable $ne) {
                            Log::warning("Negotiation status update failed: " . $ne->getMessage());
                        }
                    }
                }

                // Update Lead info & status to 'booking'
                $lead = Lead::find($validated['lead_id']);
                if ($lead) {
                    $leadData = ['status' => 'booking'];
                    $optFields = [
                        'identity_number' => $validated['buyer_nik'] ?? null,
                        'npwp' => $validated['buyer_npwp'] ?? null,
                        'address' => $validated['buyer_address'] ?? null,
                        'job' => $validated['buyer_job'] ?? null,
                    ];
                    foreach ($optFields as $lCol => $lVal) {
                        if (!empty($lVal) && Schema::hasColumn('leads', $lCol)) {
                            $leadData[$lCol] = $lVal;
                        }
                    }
                    try {
                        $lead->update($leadData);
                    } catch (\Throwable $le) {
                        Log::warning("Lead update failed: " . $le->getMessage());
                    }
                }

                // 2. Update Unit Status to 'hold'
                try {
                    $unit->update([
                        'status' => 'hold',
                        'held_by' => $validated['booked_by'],
                        'held_until' => now()->addDays(2),
                    ]);
                } catch (\Throwable $ue) {
                    Log::warning("Unit update to hold failed: " . $ue->getMessage());
                }

                try {
                    AuditLog::record('booking_created', $booking, null, $booking->toArray());
                } catch (\Throwable $ae) {
                    Log::warning("AuditLog recording failed: " . $ae->getMessage());
                }

                $autoApprove = $request->boolean('auto_approve');

                // Always persist payment schedules on creation so Show, SPR, and SPK match the simulated terms 100%
                $this->generateSchedules($booking, $autoApprove, $isCustomTerms ? $customSchedules : null);

                if ($autoApprove) {
                    $booking->update([
                        'status' => 'approved',
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    $unit->update(['status' => 'booked']);
                    $booking->lead?->recalculateScore();
                    AuditLog::record('booking_approved', $booking);
                }

                if ($request->input('redirect_to') === 'lead' && $booking->lead_id) {
                    return redirect()->route('leads.show', $booking->lead_id)
                        ->with('success', "Surat Pesanan Rumah (SPR #{$booking->spk_number}) berhasil diterbitkan!");
                }

                return redirect()->route('bookings.index')->with('success', 'Booking berhasil diajukan.');
            });
        } catch (\Throwable $e) {
            Log::error("Failed to store booking: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return back()->withInput()->with('error', 'Gagal membuat booking: ' . $e->getMessage());
        }
    }

    public function show(Booking $booking)
    {
        try {
            // Self-healing: Clean up duplicate UTJ rows (#0) if any exist from legacy operations
            $utjSchedules = $booking->paymentSchedules()->where('installment_number', 0)->orderBy('id', 'asc')->get();
            if ($utjSchedules->count() > 1) {
                foreach ($utjSchedules->slice(1) as $dupUtj) {
                    $dupUtj->delete();
                }
            }

            // Self-healing: Ensure UTJ Schedule and Transaction exist for approved bookings
            if ($booking->status === 'approved' || $booking->status === 'pending') {
                $utjSched = $booking->paymentSchedules()->where('installment_number', 0)->first();
                if (!$utjSched) {
                    $utjSched = $booking->paymentSchedules()->create([
                        'installment_number' => 0,
                        'label' => 'Booking Fee (UTJ)',
                        'amount' => $booking->booking_fee,
                        'due_date' => $booking->booking_date ?? now()->format('Y-m-d'),
                        'status' => 'paid',
                    ]);
                }

                $paidSchedules = $booking->paymentSchedules()->where('status', 'paid')->get();
                foreach ($paidSchedules as $pSched) {
                    if (empty($pSched->paid_date) && !empty($pSched->due_date)) {
                        $pSched->update(['paid_date' => $pSched->due_date]);
                    }
                    if (!$booking->transactions()->where('payment_schedule_id', $pSched->id)->exists()) {
                        $recordedBy = auth()->id() ?? $booking->booked_by ?? \App\Models\User::first()?->id ?? 1;
                        $label = $pSched->label ?: 'Pembayaran';
                        $note = str_starts_with(strtolower($label), 'pembayaran') ? $label : ("Pembayaran " . $label);
                        $tx = new \App\Models\Transaction([
                            'booking_id' => $booking->id,
                            'payment_schedule_id' => $pSched->id,
                            'amount' => $pSched->amount,
                            'payment_method' => 'transfer',
                            'notes' => $note,
                            'recorded_by' => $recordedBy,
                        ]);
                        $tx->created_at = \Carbon\Carbon::parse($pSched->paid_date ?? $pSched->due_date ?? now())->setTimeFrom(now());
                        $tx->save();
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Self-healing paid payment schedules failed in BookingController@show: ' . $e->getMessage());
        }

        $relations = [
            'unit.project', 'unit.unitType', 'lead', 'bookedBy', 'inhousePic', 'approvedBy',
            'paymentSchedules.transactions',
            'transactions', 'documents'
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn('bookings', 'bank_account_id')) {
            $relations[] = 'bankAccount';
        }
        $booking->load($relations);

        $bankAccountsAll = [];
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('bank_accounts')) {
                $bankAccountsAll = \App\Models\BankAccount::latest()->get();
            }
        } catch (\Throwable $e) {
            $bankAccountsAll = [];
        }

        $units = \App\Models\Unit::selectableForLov($booking->unit_id)
            ->when($booking->project_id, fn ($q) => $q->where('project_id', $booking->project_id))
            ->select('id', 'block', 'number', 'floor', 'final_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name', 'unitType:id,name'])
            ->orderByUnitNumber()
            ->get();

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
            'bank_accounts_all' => $bankAccountsAll,
            'units' => $units,
            'signatureConfig' => \App\Models\Setting::getSignatureConfig(),
        ]);
    }

    /**
     * Change unit for a booking
     */
    public function changeUnit(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if (in_array($booking->status, ['cancelled', 'rejected'])) {
            return back()->with('error', 'Booking yang dibatalkan atau ditolak tidak dapat diubah unitnya.');
        }

        if ($booking->unit_id == $validated['unit_id']) {
            return back()->with('info', 'Unit yang dipilih sama dengan unit booking saat ini.');
        }

        $newUnit = \App\Models\Unit::with('project')->findOrFail($validated['unit_id']);
        $newUnitHasLocks = Booking::withoutGlobalScopes()
            ->where('unit_id', $newUnit->id)
            ->whereNull('deleted_at')
            ->where('id', '!=', $booking->id)
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('pending', 'approved', 'completed', 'booked', 'sold')")
            ->exists()
            || Reservation::withoutGlobalScopes()
                ->where('unit_id', $newUnit->id)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->exists();

        if ($newUnit->status !== 'available' || $newUnitHasLocks) {
            return back()->with('error', "Unit {$newUnit->code} saat ini tidak berstatus Available (Status: {$newUnit->status}).");
        }

        $oldUnit = \App\Models\Unit::find($booking->unit_id);

        // 1. Update booking unit first, then reconcile old & new unit statuses cleanly
        $newUnitStatus = $booking->status === 'approved' ? 'booked' : 'hold';
        $booking->update([
            'unit_id' => $newUnit->id,
            'project_id' => $newUnit->project_id,
        ]);

        if ($oldUnit) {
            $oldUnit->syncStatusFromRecords($booking->id);
        }

        // 2. Lock new unit
        $newUnit->update([
            'status' => $newUnitStatus,
            'held_by' => $booking->booked_by ?? auth()->id(),
            'held_until' => now()->addDays(7),
        ]);

        // 4. Record Lead Activity
        if ($booking->lead_id) {
            $reasonText = !empty($validated['reason']) ? " Alasan: {$validated['reason']}" : "";
            \App\Models\LeadActivity::create([
                'lead_id' => $booking->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "🔄 Unit Booking / SPK (#{$booking->spk_number}) dipindahkan dari " . ($oldUnit ? $oldUnit->code : "Unit #{$booking->unit_id}") . " ke {$newUnit->code}.{$reasonText}",
            ]);
        }

        \App\Models\AuditLog::record('booking_unit_changed', $booking, ['old_unit_id' => $oldUnit?->id], ['new_unit_id' => $newUnit->id]);

        return back()->with('success', "Unit booking berhasil dipindahkan ke {$newUnit->code}!");
    }

    public function updateSprTemplate(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'spk_number' => [
                'nullable',
                'string',
                'max:100',
                \Illuminate\Validation\Rule::unique('bookings', 'spk_number')->ignore($booking->id),
            ],
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'bank_account_utj_id' => 'nullable|exists:bank_accounts,id',
            'bank_account_dp_id' => 'nullable|exists:bank_accounts,id',
            'bank_account_installment_id' => 'nullable|exists:bank_accounts,id',
            'buyer_nik' => 'nullable|string|max:50',
            'buyer_npwp' => 'nullable|string|max:50',
            'buyer_address' => 'nullable|string',
            'buyer_job' => 'nullable|string|max:100',
            'secondary_name' => 'nullable|string|max:255',
            'secondary_nik' => 'nullable|string|max:50',
            'secondary_npwp' => 'nullable|string|max:50',
            'secondary_phone' => 'nullable|string|max:20',
            'secondary_relationship' => 'nullable|string|max:100',
            'secondary_address' => 'nullable|string',
            'secondary_email' => 'nullable|email|max:255',
            'spr_date' => 'nullable|date',
            'spr_schedule_dates' => 'nullable|array',
            'spr_terms_conditions' => 'nullable|array',
            'spr_bank_info' => 'nullable|array',
            'spr_special_offer' => 'nullable|array',
            'special_bonus_items' => 'nullable|array',
            'special_package_items' => 'nullable|array',
            'sig1_title' => 'nullable|string|max:100',
            'sig1_name' => 'nullable|string|max:255',
            'sig2_title' => 'nullable|string|max:100',
            'sig2_name' => 'nullable|string|max:255',
            'sig3_title' => 'nullable|string|max:100',
            'sig3_name' => 'nullable|string|max:255',
            'sig4_title' => 'nullable|string|max:100',
            'sig4_name' => 'nullable|string|max:255',
            'sigs_city' => 'nullable|string|max:100',
            'unit_certificate_status' => 'nullable|string|max:100',
            'unit_certificate_number' => 'nullable|string|max:100',
            'receipt_settings' => 'nullable|array',
            'base_price' => 'nullable|numeric|min:0',
            'ppn_amount' => 'nullable|numeric|min:0',
            'bphtb_amount' => 'nullable|numeric|min:0',
            'ajb_bbn_amount' => 'nullable|numeric|min:0',
            'other_legal_fees' => 'nullable|numeric|min:0',
            'final_price' => 'nullable|numeric|min:0',
            'sync_schedules' => 'nullable|boolean',
        ]);

        try {
            DB::transaction(function () use ($booking, $validated) {
                $data = $validated;

                if (array_key_exists('unit_certificate_status', $data) || array_key_exists('unit_certificate_number', $data)) {
                    if ($booking->unit) {
                        $unitUpdates = [];
                        if (isset($data['unit_certificate_status'])) {
                            $unitUpdates['certificate_status'] = $data['unit_certificate_status'];
                        }
                        if (isset($data['unit_certificate_number'])) {
                            $unitUpdates['certificate_number'] = $data['unit_certificate_number'];
                        }
                        if (!empty($unitUpdates)) {
                            $booking->unit->update($unitUpdates);
                        }
                    }
                    unset($data['unit_certificate_status'], $data['unit_certificate_number']);
                }

                if (isset($data['base_price']) && isset($data['final_price'])) {
                    $priceChanged = (float)$data['final_price'] != (float)$booking->final_price || (float)$data['base_price'] != (float)$booking->base_price;
                    $shouldSync = array_key_exists('sync_schedules', $data) ? (bool)$data['sync_schedules'] : false;
                    if ($priceChanged || $shouldSync) {
                        $this->applyFinancialUpdates($booking, [
                            'base_price' => $data['base_price'],
                            'ppn_amount' => $data['ppn_amount'] ?? 0,
                            'bphtb_amount' => $data['bphtb_amount'] ?? 0,
                            'ajb_bbn_amount' => $data['ajb_bbn_amount'] ?? 0,
                            'other_legal_fees' => $data['other_legal_fees'] ?? 0,
                            'final_price' => $data['final_price'],
                            'sync_schedules' => $shouldSync,
                        ]);
                    }
                }
                unset(
                    $data['base_price'],
                    $data['ppn_amount'],
                    $data['bphtb_amount'],
                    $data['ajb_bbn_amount'],
                    $data['other_legal_fees'],
                    $data['final_price'],
                    $data['sync_schedules']
                );

                // Never overwrite NOT NULL spk_number with null/empty string
                if (array_key_exists('spk_number', $data)) {
                    $trimmedSpk = trim((string)($data['spk_number'] ?? ''));
                    if ($trimmedSpk === '') {
                        unset($data['spk_number']);
                    } else {
                        $data['spk_number'] = $trimmedSpk;
                    }
                }

                $sprBankInfo = is_array($booking->spr_bank_info) ? $booking->spr_bank_info : [];
                if (isset($data['spr_bank_info']) && is_array($data['spr_bank_info'])) {
                    $sprBankInfo = array_merge($sprBankInfo, $data['spr_bank_info']);
                }

                if (!empty($data['bank_account_id'])) {
                    $bankAcc = \App\Models\BankAccount::find($data['bank_account_id']);
                    if ($bankAcc) {
                        $sprBankInfo['main'] = [
                            'bank_name' => $bankAcc->bank_name,
                            'account_number' => $bankAcc->account_number,
                            'account_holder' => $bankAcc->account_holder,
                        ];
                        $sprBankInfo['bank_name'] = $bankAcc->bank_name;
                        $sprBankInfo['account_number'] = $bankAcc->account_number;
                        $sprBankInfo['account_holder'] = $bankAcc->account_holder;
                    }
                }

                if (!empty($data['bank_account_utj_id'])) {
                    $bankAccUtj = \App\Models\BankAccount::find($data['bank_account_utj_id']);
                    if ($bankAccUtj) {
                        $sprBankInfo['utj'] = [
                            'bank_name' => $bankAccUtj->bank_name,
                            'account_number' => $bankAccUtj->account_number,
                            'account_holder' => $bankAccUtj->account_holder,
                        ];
                    }
                }

                if (!empty($data['bank_account_dp_id'])) {
                    $bankAccDp = \App\Models\BankAccount::find($data['bank_account_dp_id']);
                    if ($bankAccDp) {
                        $sprBankInfo['dp'] = [
                            'bank_name' => $bankAccDp->bank_name,
                            'account_number' => $bankAccDp->account_number,
                            'account_holder' => $bankAccDp->account_holder,
                        ];
                    }
                }

                if (!empty($data['bank_account_installment_id'])) {
                    $bankAccInst = \App\Models\BankAccount::find($data['bank_account_installment_id']);
                    if ($bankAccInst) {
                        $sprBankInfo['installment'] = [
                            'bank_name' => $bankAccInst->bank_name,
                            'account_number' => $bankAccInst->account_number,
                            'account_holder' => $bankAccInst->account_holder,
                        ];
                    }
                }

                unset($data['bank_account_utj_id'], $data['bank_account_dp_id'], $data['bank_account_installment_id']);
                $data['spr_bank_info'] = $sprBankInfo;

                $safeUpdates = [];
                foreach ($data as $col => $val) {
                    if (Schema::hasColumn('bookings', $col)) {
                        $safeUpdates[$col] = $val;
                    }
                }
                if (!empty($safeUpdates)) {
                    $booking->update($safeUpdates);
                }

                // Pastikan update data pemesan di SPR juga tersinkronisasi ke profil lead konsumen
                if ($booking->lead) {
                    $leadUpdates = [];
                    if (!empty($data['buyer_address']) && Schema::hasColumn('leads', 'address')) {
                        $leadUpdates['address'] = $data['buyer_address'];
                    }
                    if (!empty($data['buyer_nik']) && Schema::hasColumn('leads', 'identity_number')) {
                        $leadUpdates['identity_number'] = $data['buyer_nik'];
                    }
                    if (!empty($data['buyer_npwp']) && Schema::hasColumn('leads', 'npwp')) {
                        $leadUpdates['npwp'] = $data['buyer_npwp'];
                    }
                    if (!empty($data['buyer_job']) && Schema::hasColumn('leads', 'job')) {
                        $leadUpdates['job'] = $data['buyer_job'];
                    }
                    if (!empty($leadUpdates)) {
                        $booking->lead->update($leadUpdates);
                    }
                }
            });
        } catch (\Throwable $e) {
            Log::error("Failed to update SPR template for booking #{$booking->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->withInput()->with('error', 'Gagal menyimpan template SPR: ' . $e->getMessage());
        }

        return back()->with('success', 'Template & Parameter SPR khusus booking ini berhasil diperbarui.');
    }

    public function resetSignature(Request $request, Booking $booking)
    {
        $role = $request->input('role', 'customer');
        if ($role === 'customer') {
            $booking->sig4_image = null;
            $booking->customer_signed_at = null;
            $booking->sig4_name = $booking->lead?->name;
            $booking->save();
            return back()->with('success', 'Tanda tangan digital konsumen berhasil direset. Silakan minta konsumen menandatangani ulang SPR.');
        } elseif ($role === 'agent') {
            $booking->sig3_image = null;
            $booking->agent_signed_at = null;
            $booking->save();
            return back()->with('success', 'Tanda tangan digital agent berhasil direset.');
        }
        return back();
    }

    public function updateFinancial(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'base_price' => 'required|numeric|min:0',
            'ppn_amount' => 'nullable|numeric|min:0',
            'bphtb_amount' => 'nullable|numeric|min:0',
            'ajb_bbn_amount' => 'nullable|numeric|min:0',
            'other_legal_fees' => 'nullable|numeric|min:0',
            'final_price' => 'required|numeric|min:0',
            'sync_schedules' => 'nullable|boolean',
        ]);

        $this->applyFinancialUpdates($booking, $validated);

        return back()->with('success', 'Rincian biaya, PPN, BPHTB, dan Total Kesepakatan (All-in) berhasil diperbarui.');
    }

    private function applyFinancialUpdates(Booking $booking, array $validated)
    {
        DB::transaction(function () use ($booking, $validated) {
            $newFinalPrice = (float)$validated['final_price'];
            $basePrice = (float)$validated['base_price'];
            $ppnAmount = (float)($validated['ppn_amount'] ?? 0);
            $bphtbAmount = (float)($validated['bphtb_amount'] ?? 0);
            $ajbBbnAmount = (float)($validated['ajb_bbn_amount'] ?? 0);
            $otherFees = (float)($validated['other_legal_fees'] ?? 0);

            $booking->update([
                'base_price' => $basePrice,
                'ppn_amount' => $ppnAmount,
                'bphtb_amount' => $bphtbAmount,
                'ajb_bbn_amount' => $ajbBbnAmount,
                'other_legal_fees' => $otherFees,
                'final_price' => $newFinalPrice,
            ]);

            // Recalculate agent commission based on new final price
            if ($booking->booked_by) {
                $agent = \App\Models\User::find($booking->booked_by);
                if ($agent) {
                    $agent->load(['brokerCompany', 'masterLead']);
                    $effectiveRate = $agent->effective_commission_rate;
                    $promoBonus = (float)($agent->custom_bonus ?? 0);
                    $newBaseCommission = $newFinalPrice * ($effectiveRate / 100);
                    $newTotalCommission = $newBaseCommission + $promoBonus;

                    $booking->update(['commission_amount' => $newTotalCommission]);

                    \App\Models\Commission::where('booking_id', $booking->id)
                        ->where('user_id', $booking->booked_by)
                        ->update([
                            'amount' => $newTotalCommission,
                            'base_commission' => $newBaseCommission,
                        ]);
                }
            }

            // Sync payment schedules if requested (default true)
            $syncSchedules = array_key_exists('sync_schedules', $validated) ? (bool)$validated['sync_schedules'] : true;
            if ($syncSchedules) {
                $taxTotal = $ppnAmount + $bphtbAmount + $ajbBbnAmount + $otherFees;

                // Handle Tax Schedule (installment #99)
                $taxSchedule = $booking->paymentSchedules()->where('installment_number', 99)->first();
                if ($taxTotal <= 0) {
                    if ($taxSchedule && $taxSchedule->status !== 'paid') {
                        $taxSchedule->delete();
                    }
                } else {
                    if ($taxSchedule) {
                        if ($taxSchedule->status !== 'paid') {
                            $taxSchedule->update(['amount' => $taxTotal]);
                        }
                    } else {
                        $booking->paymentSchedules()->create([
                            'installment_number' => 99,
                            'label' => 'Pajak & Biaya Legal (PPN, BPHTB, AJB)',
                            'amount' => $taxTotal,
                            'due_date' => now()->addDays(30)->format('Y-m-d'),
                            'status' => 'upcoming',
                        ]);
                    }
                }

                // Balance unpaid regular installments
                $bookingFee = (float)$booking->booking_fee;
                $paidSum = (float)$booking->paymentSchedules()
                    ->where('status', 'paid')
                    ->where('installment_number', '!=', 0)
                    ->where('installment_number', '!=', 99)
                    ->sum('amount');

                $unpaidSchedules = $booking->paymentSchedules()
                    ->where('status', '!=', 'paid')
                    ->where('installment_number', '!=', 0)
                    ->where('installment_number', '!=', 99)
                    ->orderBy('installment_number', 'asc')
                    ->get();

                if ($unpaidSchedules->count() > 0) {
                    $taxScheduleActive = $booking->paymentSchedules()->where('installment_number', 99)->exists();
                    $targetForInstallments = $taxScheduleActive 
                        ? ($basePrice - $bookingFee - $paidSum) 
                        : ($newFinalPrice - $bookingFee - $paidSum);
                    $targetForInstallments = max(0, $targetForInstallments);

                    // Separate DP rows, Bank KPR rows, and regular Cicilan rows
                    $dpRows = $unpaidSchedules->filter(fn($s) => str_contains(strtolower($s->label), 'dp') || str_contains(strtolower($s->label), 'uang muka'));
                    $bankRows = $unpaidSchedules->filter(fn($s) => str_contains(strtolower($s->label), 'kpr') || str_contains(strtolower($s->label), 'bank') || str_contains(strtolower($s->label), 'akad'));
                    $cicilanRows = $unpaidSchedules->filter(fn($s) => 
                        !str_contains(strtolower($s->label), 'dp') && 
                        !str_contains(strtolower($s->label), 'uang muka') && 
                        !str_contains(strtolower($s->label), 'kpr') && 
                        !str_contains(strtolower($s->label), 'bank') && 
                        !str_contains(strtolower($s->label), 'akad')
                    );

                    if ($bankRows->count() > 0) {
                        // KPR scheme: Keep DP rows intact! Only adjust the Bank loan row
                        $unpaidDpSum = (float)$dpRows->sum('amount');
                        $bankTarget = max(0, $targetForInstallments - $unpaidDpSum);
                        foreach ($bankRows as $bRow) {
                            $bRow->update(['amount' => $bankTarget]);
                        }
                    } elseif ($cicilanRows->count() > 0 && $dpRows->count() > 0) {
                        // Cash Installment with both DP and Cicilan: Keep DP rows intact! Only adjust Cicilan rows
                        $unpaidDpSum = (float)$dpRows->sum('amount');
                        $cicilanTarget = max(0, $targetForInstallments - $unpaidDpSum);
                        $cCount = $cicilanRows->count();
                        if ($cCount > 0) {
                            $perCicilan = round($cicilanTarget / $cCount);
                            $idx = 0;
                            foreach ($cicilanRows as $cRow) {
                                $cAmount = ($idx === $cCount - 1) ? ($cicilanTarget - ($perCicilan * ($cCount - 1))) : $perCicilan;
                                $cRow->update(['amount' => max(0, $cAmount)]);
                                $idx++;
                            }
                        }
                    } else {
                        // Pure installments or pure DP: distribute equally across unpaid schedules without rounding drift
                        $count = $unpaidSchedules->count();
                        $perItem = round($targetForInstallments / $count);
                        foreach ($unpaidSchedules as $index => $item) {
                            $itemAmount = ($index === $count - 1) ? ($targetForInstallments - ($perItem * ($count - 1))) : $perItem;
                            $item->update(['amount' => max(0, $itemAmount)]);
                        }
                    }
                }
            }

            \App\Models\AuditLog::record('booking_financial_updated', $booking, null, $validated);
        });
    }

    public function approve(Booking $booking)
    {
        try {
            return DB::transaction(function () use ($booking) {
                $booking->update([
                    'status' => 'approved',
                    'approved_by' => auth()->id(),
                ]);

                // Recalculate commission rate and bonus
                $agent = \App\Models\User::find($booking->booked_by);
                $effectiveRate = 2.5;
                $promoBonus = 0;
                if ($agent) {
                    $agent->load(['brokerCompany', 'masterLead']);
                    $effectiveRate = $agent->effective_commission_rate;
                    $promoBonus = (float)($agent->custom_bonus ?? 0);
                }

                $baseCommission = $booking->final_price * ($effectiveRate / 100);
                $totalCommission = $baseCommission + $promoBonus;

                $masterLead = $agent?->masterLead ?? $agent?->brokerCompany?->masterLead;
                if (!$masterLead && \App\Models\Setting::get('commission_schema_config.enable_master_lead', true)) {
                    $masterLead = \App\Models\User::where('agent_type', 'master_lead')
                        ->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))
                        ->first();
                }

                $isSubAgentUnderMasterLead = ($masterLead && $masterLead->id !== $agent?->id);
                $commissionCompanyId = $booking->company_id ?? auth()->user()?->company_id;

                if ($booking->booked_by) {
                    // Record Sub-Agent / Agency Commission
                    \App\Models\Commission::updateOrCreate(
                        ['booking_id' => $booking->id, 'user_id' => $booking->booked_by],
                        [
                            'company_id' => $commissionCompanyId,
                            'broker_company_id' => $agent?->broker_company_id,
                            'amount' => $totalCommission,
                            'base_commission' => $baseCommission,
                            'promo_bonus' => $promoBonus,
                            'rate_used' => $effectiveRate,
                            'payout_recipient' => $isSubAgentUnderMasterLead ? 'sub_agent' : ($agent?->broker_company_id ? 'agency' : 'agent'),
                            'status' => 'pending',
                        ]
                    );
                }

                // Record Master Lead Overriding Fee if applicable
                if ($isSubAgentUnderMasterLead) {
                    $masterRate = (float)\App\Models\Setting::get('default_commission_rates.master_lead_overriding', ($masterLead->commission_rate > 0 ? (float)$masterLead->commission_rate : 4.5));
                    $masterTotalGross = $booking->final_price * ($masterRate / 100);

                    \App\Models\Commission::updateOrCreate(
                        ['booking_id' => $booking->id, 'user_id' => $masterLead->id],
                        [
                            'company_id' => $commissionCompanyId,
                            'broker_company_id' => null,
                            'amount' => $masterTotalGross,
                            'base_commission' => $masterTotalGross,
                            'promo_bonus' => 0,
                            'rate_used' => $masterRate,
                            'payout_recipient' => 'master_lead',
                            'status' => 'pending',
                        ]
                    );
                }

                $booking->update(['commission_amount' => $totalCommission]);

                $booking->unit?->update(['status' => 'booked']);
                // Lead tetap status 'booking' — akan berubah ke 'won' otomatis saat pembayaran lunas
                if ($booking->lead && $booking->lead->status !== 'won') {
                    $booking->lead->update(['status' => 'booking']);
                }
                $booking->lead?->recalculateScore();

                // GENERATE PAYMENT SCHEDULE
                $this->generateSchedules($booking);

                AuditLog::record('booking_approved', $booking);

                return back()->with('success', 'Booking telah disetujui dan jadwal pembayaran telah dibuat.');
            });
        } catch (\Throwable $e) {
            Log::error("Failed to approve booking #{$booking->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'Gagal menyetujui booking: ' . $e->getMessage());
        }
    }

    private function generateSchedules(Booking $booking, bool $markUtjPaid = true, ?array $customSchedules = null, bool $forceReplaceUnpaid = false)
    {
        $basePrice = (float)($booking->base_price ?: $booking->final_price);
        $taxLegalTotal = (float)($booking->ppn_amount ?? 0) + (float)($booking->bphtb_amount ?? 0) + (float)($booking->ajb_bbn_amount ?? 0) + (float)($booking->other_legal_fees ?? 0);
        $bookingDate = \Carbon\Carbon::parse($booking->booking_date ?? now());
        $companyId = $booking->company_id ?? auth()->user()?->company_id;

        // 1. Booking Fee (UTJ) - Installment 0
        $utjSchedule = $booking->paymentSchedules()->where('installment_number', 0)->first();
        if (!$utjSchedule) {
            $utjSchedule = $booking->paymentSchedules()->create([
                'company_id' => $companyId,
                'installment_number' => 0,
                'label' => 'Booking Fee (UTJ)',
                'amount' => $booking->booking_fee,
                'due_date' => $bookingDate->format('Y-m-d'),
                'status' => $markUtjPaid ? 'paid' : 'upcoming',
                'paid_date' => $markUtjPaid ? $bookingDate->format('Y-m-d') : null,
            ]);
        } elseif ($markUtjPaid && $utjSchedule->status !== 'paid') {
            $utjSchedule->update([
                'status' => 'paid',
                'paid_date' => $bookingDate->format('Y-m-d'),
            ]);
        }

        if ($markUtjPaid && $utjSchedule && !$booking->transactions()->where('payment_schedule_id', $utjSchedule->id)->exists()) {
            $utjTx = new \App\Models\Transaction([
                'company_id' => $companyId,
                'booking_id' => $booking->id,
                'payment_schedule_id' => $utjSchedule->id,
                'amount' => $utjSchedule->amount,
                'payment_method' => 'transfer',
                'notes' => 'Otomatis dari Booking Fee (UTJ)',
                'recorded_by' => auth()->id() ?? $booking->booked_by,
            ]);
            $utjTx->created_at = $bookingDate->copy()->setTimeFrom(now());
            $utjTx->save();

            if (empty($utjSchedule->paid_date)) {
                $utjSchedule->update(['paid_date' => $bookingDate->format('Y-m-d')]);
            }
        }

        // 2. Taxes & Legal (separate item, due within 30 days)
        $taxSchedule = $booking->paymentSchedules()->where('installment_number', 99)->first();
        if ($taxLegalTotal > 0) {
            if ($taxSchedule) {
                if ($taxSchedule->status !== 'paid') {
                    $taxSchedule->update(['amount' => $taxLegalTotal]);
                }
            } else {
                $booking->paymentSchedules()->create([
                    'company_id' => $companyId,
                    'installment_number' => 99,
                    'label' => 'Pajak & Biaya Legal (PPN, BPHTB, AJB)',
                    'amount' => $taxLegalTotal,
                    'due_date' => $bookingDate->copy()->addDays(30)->format('Y-m-d'),
                    'status' => 'upcoming',
                ]);
            }
        } elseif ($taxSchedule && $taxSchedule->status !== 'paid') {
            $taxSchedule->delete();
        }

        if ($forceReplaceUnpaid) {
            $booking->paymentSchedules()
                ->where('status', '!=', 'paid')
                ->where('installment_number', '!=', 0)
                ->delete();
        }

        $hasUnitSchedules = $booking->paymentSchedules()
            ->whereNotIn('installment_number', [0, 99])
            ->exists();

        // Preserve existing schedules (e.g. custom terms saved during store() before approve())
        if ($hasUnitSchedules && !$forceReplaceUnpaid) {
            return;
        }

        // 3. Unit price installments (DP starts 1 month AFTER booking_date)
        $targetPrice = $booking->final_price > 0 ? (float)$booking->final_price : ($booking->base_price ?: 0);
        $unitTarget = ($taxLegalTotal > 0 && $targetPrice > $basePrice && $basePrice > 0) ? $basePrice : $targetPrice;
        $paidUnitAmount = $forceReplaceUnpaid
            ? (float)$booking->paymentSchedules()->where('status', 'paid')->whereNotIn('installment_number', [0, 99])->sum('amount')
            : 0.0;
        $maxExistingNum = $forceReplaceUnpaid
            ? (int)($booking->paymentSchedules()->whereNotIn('installment_number', [99])->max('installment_number') ?? 0)
            : 0;
        $remaining = max(0, $unitTarget - (float)$booking->booking_fee - $paidUnitAmount);

        if ($booking->payment_scheme === 'kpr') {
            $dpTotal = $booking->dp_amount > 0 ? (float)$booking->dp_amount : ($basePrice * 0.10);
            $dpTenor = $booking->dp_installment_months > 0 ? (int)$booking->dp_installment_months : 3;
            $dpPerMonth = round($dpTotal / $dpTenor);

            for ($i = 1; $i <= $dpTenor; $i++) {
                $amount = ($i === $dpTenor) ? ($dpTotal - ($dpPerMonth * ($dpTenor - 1))) : $dpPerMonth;
                $booking->paymentSchedules()->create([
                    'company_id' => $companyId,
                    'installment_number' => $maxExistingNum + $i,
                    'label' => $dpTenor > 1 ? "DP $i" : "DP 1",
                    'amount' => $amount,
                    'due_date' => $bookingDate->copy()->addMonths($i)->format('Y-m-d'),
                    'status' => 'upcoming',
                ]);
            }

            $bankAmount = max(0, $remaining - $dpTotal);
            $booking->paymentSchedules()->create([
                'company_id' => $companyId,
                'installment_number' => $maxExistingNum + $dpTenor + 1,
                'label' => 'Pencairan KPR (Bank)',
                'amount' => $bankAmount,
                'due_date' => $bookingDate->copy()->addMonths($dpTenor + 1)->format('Y-m-d'),
                'status' => 'upcoming',
            ]);
        } elseif ($booking->payment_scheme === 'cash') {
            $booking->paymentSchedules()->create([
                'company_id' => $companyId,
                'installment_number' => $maxExistingNum + 1,
                'label' => 'Pelunasan Cash Keras',
                'amount' => max(0, $remaining),
                'due_date' => $bookingDate->copy()->addDays(14)->format('Y-m-d'),
                'status' => 'upcoming',
            ]);
        } elseif (!empty($customSchedules)) {
            foreach (array_values($customSchedules) as $idx => $item) {
                $num = $maxExistingNum + $idx + 1;
                $dueDate = !empty($item['due_date'])
                    ? \Carbon\Carbon::parse($item['due_date'])->format('Y-m-d')
                    : $bookingDate->copy()->addMonths($idx + 1)->format('Y-m-d');
                $booking->paymentSchedules()->create([
                    'company_id' => $companyId,
                    'installment_number' => $num,
                    'label' => trim((string)($item['label'] ?? "DP " . ($idx + 1))),
                    'amount' => max(0, round((float)($item['amount'] ?? 0))),
                    'due_date' => $dueDate,
                    'status' => 'upcoming',
                ]);
            }
        } else {
            // Cash Installment / In-House (Standard Mode)
            $dpTotal = $booking->dp_amount > 0 ? (float)$booking->dp_amount : 0;
            $dpTenor = $booking->dp_installment_months > 0 ? (int)$booking->dp_installment_months : ($dpTotal > 0 ? 1 : 0);
            $offsetMonths = 0;

            if ($dpTotal > 0) {
                $dpPerMonth = round($dpTotal / $dpTenor);
                for ($d = 1; $d <= $dpTenor; $d++) {
                    $amountDp = ($d === $dpTenor) ? ($dpTotal - ($dpPerMonth * ($dpTenor - 1))) : $dpPerMonth;
                    $dpLabel = $dpTenor > 1 ? "DP $d" : "DP 1";
                    $booking->paymentSchedules()->create([
                        'company_id' => $companyId,
                        'installment_number' => $maxExistingNum + $d,
                        'label' => $dpLabel,
                        'amount' => $amountDp,
                        'due_date' => $bookingDate->copy()->addMonths($d)->format('Y-m-d'),
                        'status' => 'upcoming',
                    ]);
                }
                $offsetMonths = $dpTenor;
                $remaining = max(0, $remaining - $dpTotal);
            }

            // Tenor cicilan: jika remaining > 0 dan tenor = 0, buat 1x Pelunasan Sisa Cash Bertahap
            $tenor = $booking->installment_months !== null ? (int)$booking->installment_months : ($dpTotal > 0 ? 0 : 12);
            if ($tenor > 0 && $remaining > 0) {
                $perMonth = round($remaining / $tenor);

                for ($i = 1; $i <= $tenor; $i++) {
                    $num = $maxExistingNum + $offsetMonths + $i;
                    $amount = ($i === $tenor) ? ($remaining - ($perMonth * ($tenor - 1))) : $perMonth;
                    $booking->paymentSchedules()->create([
                        'company_id' => $companyId,
                        'installment_number' => $num,
                        'label' => "Cicilan Ke-$i (dari $tenor Bulan)",
                        'amount' => $amount,
                        'due_date' => $bookingDate->copy()->addMonths($offsetMonths + $i)->format('Y-m-d'),
                        'status' => 'upcoming',
                    ]);
                }
            } elseif ($tenor === 0 && $remaining > 0) {
                $booking->paymentSchedules()->create([
                    'company_id' => $companyId,
                    'installment_number' => $maxExistingNum + $offsetMonths + 1,
                    'label' => 'Pelunasan Sisa Cash Bertahap',
                    'amount' => $remaining,
                    'due_date' => $bookingDate->copy()->addMonths($offsetMonths + 1)->format('Y-m-d'),
                    'status' => 'upcoming',
                ]);
            }
        }
    }

    public function regenerateSchedule(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'payment_scheme' => 'required|in:cash,cash_installment,kpr',
            'schedule_mode' => 'nullable|in:standard,custom_terms',
            'custom_schedules' => 'nullable|array|max:60',
            'custom_schedules.*.label' => 'required|string|max:255',
            'custom_schedules.*.amount' => 'required|numeric|min:0',
            'custom_schedules.*.due_date' => 'nullable|date',
            'installment_months' => 'nullable|integer|min:0|max:360',
            'dp_amount' => 'nullable|numeric|min:0',
            'dp_installment_months' => 'nullable|integer|min:0|max:60',
        ]);

        $isCustomTerms = ($validated['payment_scheme'] === 'cash_installment')
            && (($validated['schedule_mode'] ?? 'standard') === 'custom_terms');

        $customSchedules = [];
        if ($isCustomTerms) {
            $customSchedules = array_values(array_filter(
                $validated['custom_schedules'] ?? [],
                fn ($row) => isset($row['label']) && trim((string)$row['label']) !== ''
            ));

            if (empty($customSchedules)) {
                return back()->withErrors([
                    'custom_schedules' => 'Tambahkan minimal 1 termin pembayaran custom.',
                ]);
            }

            $basePrice = (float)($booking->base_price ?: $booking->final_price);
            $taxLegalTotal = (float)($booking->ppn_amount ?? 0) + (float)($booking->bphtb_amount ?? 0) + (float)($booking->ajb_bbn_amount ?? 0) + (float)($booking->other_legal_fees ?? 0);
            $targetPrice = $booking->final_price > 0 ? (float)$booking->final_price : $basePrice;
            $unitTarget = ($taxLegalTotal > 0 && $targetPrice > $basePrice && $basePrice > 0) ? $basePrice : $targetPrice;
            $paidUnitAmount = (float)$booking->paymentSchedules()->where('status', 'paid')->whereNotIn('installment_number', [0, 99])->sum('amount');
            $expectedRemaining = max(0, $unitTarget - (float)$booking->booking_fee - $paidUnitAmount);
            $customSum = array_sum(array_map(fn ($r) => max(0, round((float)($r['amount'] ?? 0))), $customSchedules));

            if (abs($customSum - $expectedRemaining) > 100) {
                return back()->withErrors([
                    'custom_schedules' => 'Total nominal termin custom (Rp ' . number_format($customSum, 0, ',', '.') . ') harus sama dengan sisa harga yang belum dibayar (Rp ' . number_format($expectedRemaining, 0, ',', '.') . ').',
                ]);
            }

            $validated['dp_amount'] = $customSum + $paidUnitAmount;
            $validated['dp_installment_months'] = count($customSchedules);
            $validated['installment_months'] = 0;
        }

        DB::transaction(function () use ($booking, $validated, $isCustomTerms, $customSchedules) {
            $booking->update([
                'payment_scheme' => $validated['payment_scheme'],
                'installment_months' => isset($validated['installment_months']) ? (int)$validated['installment_months'] : 0,
                'dp_amount' => $validated['dp_amount'] ?? 0,
                'dp_installment_months' => $validated['dp_installment_months'] ?? 0,
            ]);

            $markUtjPaid = $booking->status === 'approved';
            $this->generateSchedules(
                $booking,
                $markUtjPaid,
                $isCustomTerms ? $customSchedules : null,
                true
            );
            AuditLog::record('booking_schedule_regenerated', $booking, null, $validated);
        });

        return back()->with('success', 'Jadwal pembayaran berhasil di-regenerate sesuai skema baru.');
    }

    public function addScheduleRow(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
        ]);

        $maxNum = $booking->paymentSchedules()->max('installment_number') ?? 0;

        $booking->paymentSchedules()->create([
            'installment_number' => $maxNum + 1,
            'label' => $validated['label'],
            'amount' => $validated['amount'],
            'due_date' => $validated['due_date'],
            'status' => 'upcoming',
        ]);

        return back()->with('success', 'Baris tagihan baru berhasil ditambahkan.');
    }

    public function updateScheduleRow(Request $request, \App\Models\PaymentSchedule $paymentSchedule)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'required|date',
            'status' => 'required|in:upcoming,paid,overdue,partial',
        ]);

        $paymentSchedule->update($validated);

        if ($validated['status'] === 'paid' && empty($paymentSchedule->paid_date)) {
            $paymentSchedule->update(['paid_date' => $validated['due_date']]);
        }

        // Auto-detect LUNAS when schedule is manually set to 'paid'
        if ($validated['status'] === 'paid') {
            $booking = $paymentSchedule->booking;
            if ($booking && $booking->status === 'approved') {
                $allSchedules = $booking->paymentSchedules()->get();
                $allPaid = $allSchedules->count() > 0 && $allSchedules->every(fn ($s) => $s->status === 'paid');

                if ($allPaid) {
                    $booking->update(['status' => 'completed']);
                    if ($booking->unit) {
                        $booking->unit->update(['status' => 'sold', 'held_by' => null, 'held_until' => null]);
                    }
                    if ($booking->lead) {
                        $booking->lead->update(['status' => 'won']);
                        $booking->lead->recalculateScore();
                    }
                    AuditLog::record('booking_completed', $booking, null, [
                        'total_paid' => $booking->total_paid,
                        'completed_at' => now()->toDateTimeString(),
                    ]);
                }
            }
        }

        return back()->with('success', 'Rincian tagihan berhasil diperbarui.');
    }

    public function deleteScheduleRow(\App\Models\PaymentSchedule $paymentSchedule)
    {
        $booking = $paymentSchedule->booking;
        $utjCount = $booking->paymentSchedules()->where('installment_number', 0)->count();

        if ($paymentSchedule->installment_number === 0 && $utjCount <= 1 && $paymentSchedule->status === 'paid') {
            return back()->with('error', 'Tagihan Booking Fee (UTJ) utama yang sudah lunas tidak dapat dihapus.');
        }

        $paymentSchedule->delete();
        return back()->with('success', 'Baris tagihan berhasil dihapus.');
    }

    public function sendScheduleEmail(\App\Models\PaymentSchedule $paymentSchedule)
    {
        $paymentSchedule->load(['booking.lead', 'booking.unit.project']);
        $clientEmail = $paymentSchedule->booking?->lead?->email;

        if (empty($clientEmail)) {
            return back()->with('error', 'Gagal mengirim email: Alamat email konsumen belum diisi di data Lead.');
        }

        try {
            \Illuminate\Support\Facades\Mail::to($clientEmail)->send(
                new \App\Mail\BillingInvoiceMail($paymentSchedule, 'manual')
            );
            AuditLog::record('billing_email_sent', $paymentSchedule->booking, null, ['schedule_id' => $paymentSchedule->id, 'email' => $clientEmail]);
            return back()->with('success', "Invoice tagihan berhasil dikirimkan ke email {$clientEmail}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal mengirim email tagihan: ' . $e->getMessage());
        }
    }

    public function reject(Request $request, Booking $booking)
    {
        $request->validate(['reason' => 'required|string']);

        return DB::transaction(function () use ($booking, $request) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_reason' => $request->reason,
            ]);

            // Kembalikan status unit sesuai sisa record aktif (tidak menimpa jika masih ada booking lain/sold)
            if ($booking->unit) {
                $booking->unit->syncStatusFromRecords($booking->id);
                $booking->unit->project?->recalculateUnits();
            }

            if ($booking->lead) {
                $booking->lead->update(['status' => 'negotiation']);
            }

            AuditLog::record('booking_rejected', $booking, null, ['reason' => $request->reason]);

            return back()->with('success', 'Booking / SPR telah ditolak. Unit properti otomatis kembali Available.');
        });
    }

    public function cancel(Request $request, Booking $booking)
    {
        $request->validate(['reason' => 'required|string']);

        return DB::transaction(function () use ($booking, $request) {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_reason' => $request->reason,
            ]);

            // Kembalikan status unit sesuai sisa record aktif (tidak menimpa jika masih ada booking lain/sold)
            if ($booking->unit) {
                $booking->unit->syncStatusFromRecords($booking->id);
                $booking->unit->project?->recalculateUnits();
            }
            
            // Kembalikan status lead ke negotiation agar sales dapat follow up kembali
            if ($booking->lead) {
                $booking->lead->update(['status' => 'negotiation']);
            }

            AuditLog::record('booking_cancelled', $booking, null, ['reason' => $request->reason]);

            return back()->with('success', 'Booking & SPR telah dibatalkan. Unit properti telah otomatis dikembalikan menjadi Available.');
        });
    }

    public function updateKpr(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'kpr_status' => 'required|string',
            'kpr_bank_name' => 'nullable|string',
            'kpr_plafon_amount' => 'nullable|numeric',
            'kpr_notes' => 'nullable|string',
        ]);

        $old = $booking->toArray();
        $booking->update($validated);
        
        AuditLog::record('kpr_status_updated', $booking, $old, $validated);

        return back()->with('success', 'Progres KPR berhasil diperbarui.');
    }

    public function destroy(Booking $booking)
    {
        return DB::transaction(function () use ($booking) {
            // 1. Sinkronkan status unit (kembali ke available hanya jika tidak ada booking/reservasi aktif lain)
            if ($booking->unit) {
                $booking->unit->syncStatusFromRecords($booking->id);
                $booking->unit->project?->recalculateUnits();
            }

            // 2. Kembalikan status lead ke negotiation agar sales tetap bisa follow up
            if ($booking->lead) {
                $booking->lead->update(['status' => 'negotiation']);
            }

            // 3. Catat ke audit log untuk keandalan jejak riwayat
            AuditLog::record('deleted', $booking, $booking->toArray());

            // 4. Lakukan Soft Delete (tidak menghapus permanen agar riwayat transaksi/laporan tetap utuh)
            $booking->delete();

            return redirect()->route('bookings.index')->with('success', 'Booking berhasil dihapus. Unit properti telah dilepas kembali menjadi Available.');
        });
    }
}
