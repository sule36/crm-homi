<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Unit;
use App\Models\Lead;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $bookings = Booking::with(['unit.project', 'lead', 'bookedBy'])
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
        return Inertia::render('Bookings/Create', [
            'unit' => $request->unit_id ? Unit::with('project', 'unitType')->find($request->unit_id) : null,
            'lead' => $request->lead_id ? Lead::find($request->lead_id) : null,
            'availableUnits' => Unit::where('status', 'available')->with('project', 'unitType')->get(),
            'leads' => Lead::whereNotIn('status', ['won', 'lost'])->get(),
            'agents' => \App\Models\User::orderBy('name', 'asc')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'required|exists:leads,id',
            'booked_by' => 'required|exists:users,id',
            'booking_fee' => 'required|numeric|min:0',
            'base_price' => 'required|numeric|min:0',
            'ppn_amount' => 'nullable|numeric|min:0',
            'bphtb_amount' => 'nullable|numeric|min:0',
            'ajb_bbn_amount' => 'nullable|numeric|min:0',
            'other_legal_fees' => 'nullable|numeric|min:0',
            'final_price' => 'required|numeric|min:0',
            'payment_scheme' => 'required|in:cash,cash_installment,kpr',
            'installment_months' => 'nullable|integer|min:1|max:360',
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
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $unit = Unit::findOrFail($validated['unit_id']);
            $agent = \App\Models\User::findOrFail($validated['booked_by']);
            $agent->load('brokerCompany');
            $rate = $agent->effective_commission_rate;
            $baseCommission = $validated['final_price'] * ($rate / 100);
            $promoBonus = (float)($agent->custom_bonus ?? 0);
            $commissionAmount = $baseCommission + $promoBonus;
            
            // 1. Create Booking
            $booking = Booking::create([
                'spk_number' => Booking::generateSpkNumber($validated['project_id'] ?? null),
                'unit_id' => $validated['unit_id'],
                'lead_id' => $validated['lead_id'],
                'project_id' => $unit->project_id,
                'booked_by' => $validated['booked_by'],
                'booking_fee' => $validated['booking_fee'],
                'unit_price' => $unit->unitType->current_price ?? $validated['base_price'],
                'base_price' => $validated['base_price'],
                'ppn_amount' => $validated['ppn_amount'] ?? 0,
                'bphtb_amount' => $validated['bphtb_amount'] ?? 0,
                'ajb_bbn_amount' => $validated['ajb_bbn_amount'] ?? 0,
                'other_legal_fees' => $validated['other_legal_fees'] ?? 0,
                'final_price' => $validated['final_price'],
                'payment_scheme' => $validated['payment_scheme'],
                'installment_months' => $validated['installment_months'] ?? 12,
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
                'status' => 'pending',
                'notes' => $validated['notes'],
                'commission_amount' => $commissionAmount,
            ]);

            // Update Lead info
            $lead = \App\Models\Lead::find($validated['lead_id']);
            if ($lead) {
                $lead->update(array_filter([
                    'identity_number' => $validated['buyer_nik'] ?? $lead->identity_number,
                    'npwp' => $validated['buyer_npwp'] ?? $lead->npwp,
                    'address' => $validated['buyer_address'] ?? $lead->address,
                    'job' => $validated['buyer_job'] ?? $lead->job,
                ]));
            }

            // 2. Update Unit Status to 'hold'
            $unit->update(['status' => 'hold', 'held_by' => $validated['booked_by'], 'held_until' => now()->addDays(2)]);

            AuditLog::record('booking_created', $booking, null, $booking->toArray());

            return redirect()->route('bookings.index')->with('success', 'Booking berhasil diajukan.');
        });
    }

    public function show(Booking $booking)
    {
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

            if ($utjSched && !$booking->transactions()->where('payment_schedule_id', $utjSched->id)->exists()) {
                \App\Models\Transaction::create([
                    'booking_id' => $booking->id,
                    'payment_schedule_id' => $utjSched->id,
                    'amount' => $booking->booking_fee,
                    'payment_method' => 'cash',
                    'notes' => 'Otomatis dari Booking Fee (UTJ)',
                    'recorded_by' => auth()->id() ?? $booking->booked_by,
                ]);
            }
        }

        $booking->load([
            'unit.project', 'unit.unitType', 'lead', 'bookedBy', 'approvedBy',
            'paymentSchedules' => fn ($q) => $q->orderBy('installment_number', 'asc')->orderBy('id', 'asc'),
            'transactions', 'documents'
        ]);

        return Inertia::render('Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    public function approve(Booking $booking)
    {
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
                $agent->load('brokerCompany');
                $effectiveRate = $agent->effective_commission_rate;
                $promoBonus = (float)($agent->custom_bonus ?? 0);
            }

            $baseCommission = $booking->final_price * ($effectiveRate / 100);
            $totalCommission = $baseCommission + $promoBonus;

            // Record Commission
            \App\Models\Commission::updateOrCreate(
                ['booking_id' => $booking->id],
                [
                    'user_id' => $booking->booked_by,
                    'broker_company_id' => $agent?->broker_company_id,
                    'amount' => $totalCommission,
                    'base_commission' => $baseCommission,
                    'promo_bonus' => $promoBonus,
                    'rate_used' => $effectiveRate,
                    'payout_recipient' => $agent?->broker_company_id ? 'agency' : 'agent',
                    'status' => 'pending',
                ]
            );

            $booking->update(['commission_amount' => $totalCommission]);

            $booking->unit->update(['status' => 'booked']);
            $booking->lead->update(['status' => 'won']);
            $booking->lead->recalculateScore();

            // GENERATE PAYMENT SCHEDULE
            $this->generateSchedules($booking);

            AuditLog::record('booking_approved', $booking);

            return back()->with('success', 'Booking telah disetujui dan jadwal pembayaran telah dibuat.');
        });
    }

    private function generateSchedules(Booking $booking)
    {
        $basePrice = $booking->base_price ?: $booking->final_price;
        $taxLegalTotal = ($booking->ppn_amount ?? 0) + ($booking->bphtb_amount ?? 0) + ($booking->ajb_bbn_amount ?? 0) + ($booking->other_legal_fees ?? 0);
        $bookingDate = \Carbon\Carbon::parse($booking->booking_date ?? now());

        // 1. Booking Fee (UTJ) - Installment 0
        $utjSchedule = $booking->paymentSchedules()->where('installment_number', 0)->first();
        if (!$utjSchedule) {
            $utjSchedule = $booking->paymentSchedules()->create([
                'installment_number' => 0,
                'label' => 'Booking Fee (UTJ)',
                'amount' => $booking->booking_fee,
                'due_date' => $bookingDate->format('Y-m-d'),
                'status' => 'paid',
            ]);

            if (!$booking->transactions()->where('payment_schedule_id', $utjSchedule->id)->exists()) {
                \App\Models\Transaction::create([
                    'booking_id' => $booking->id,
                    'payment_schedule_id' => $utjSchedule->id,
                    'amount' => $booking->booking_fee,
                    'payment_method' => 'cash',
                    'notes' => 'Otomatis dari Booking Fee',
                    'recorded_by' => auth()->id() ?? $booking->booked_by,
                ]);
            }
        }

        // 2. Taxes & Legal (separate item, due within 30 days)
        if ($taxLegalTotal > 0 && !$booking->paymentSchedules()->where('installment_number', 99)->exists()) {
            $booking->paymentSchedules()->create([
                'installment_number' => 99,
                'label' => 'Pajak & Biaya Legal (PPN, BPHTB, AJB)',
                'amount' => $taxLegalTotal,
                'due_date' => $bookingDate->copy()->addDays(30)->format('Y-m-d'),
                'status' => 'upcoming',
            ]);
        }

        // 3. Unit price installments (DP starts 1 month AFTER booking_date)
        $targetPrice = $booking->final_price > 0 ? $booking->final_price : ($booking->base_price ?: 0);
        $remaining = $targetPrice - $booking->booking_fee;

        if ($booking->payment_scheme === 'kpr') {
            $dpTotal = $booking->dp_amount > 0 ? $booking->dp_amount : ($basePrice * 0.10);
            $dpTenor = $booking->dp_installment_months > 0 ? (int)$booking->dp_installment_months : 3;
            $dpPerMonth = round($dpTotal / $dpTenor);

            for ($i = 1; $i <= $dpTenor; $i++) {
                $amount = ($i === $dpTenor) ? ($dpTotal - ($dpPerMonth * ($dpTenor - 1))) : $dpPerMonth;
                $booking->paymentSchedules()->create([
                    'installment_number' => $i,
                    'label' => "DP Ke-$i",
                    'amount' => $amount,
                    'due_date' => $bookingDate->copy()->addMonths($i)->format('Y-m-d'),
                    'status' => 'upcoming',
                ]);
            }

            $bankAmount = $basePrice - $booking->booking_fee - $dpTotal;
            $booking->paymentSchedules()->create([
                'installment_number' => $dpTenor + 1,
                'label' => 'Pencairan KPR (Bank)',
                'amount' => max(0, $bankAmount),
                'due_date' => $bookingDate->copy()->addMonths($dpTenor + 1)->format('Y-m-d'),
                'status' => 'upcoming',
            ]);
        } elseif ($booking->payment_scheme === 'cash') {
            $booking->paymentSchedules()->create([
                'installment_number' => 1,
                'label' => 'Pelunasan Cash Keras',
                'amount' => max(0, $remaining),
                'due_date' => $bookingDate->copy()->addDays(14)->format('Y-m-d'),
                'status' => 'upcoming',
            ]);
        } else {
            // Cash Installment / In-House
            $dpTotal = $booking->dp_amount > 0 ? $booking->dp_amount : 0;
            $dpTenor = $booking->dp_installment_months > 0 ? (int)$booking->dp_installment_months : 0;
            $offsetMonths = 0;

            if ($dpTotal > 0 && $dpTenor > 0) {
                $dpPerMonth = round($dpTotal / $dpTenor);
                for ($d = 1; $d <= $dpTenor; $d++) {
                    $amountDp = ($d === $dpTenor) ? ($dpTotal - ($dpPerMonth * ($dpTenor - 1))) : $dpPerMonth;
                    $booking->paymentSchedules()->create([
                        'installment_number' => $d,
                        'label' => "DP Ke-$d",
                        'amount' => $amountDp,
                        'due_date' => $bookingDate->copy()->addMonths($d)->format('Y-m-d'),
                        'status' => 'upcoming',
                    ]);
                }
                $offsetMonths = $dpTenor;
                $remaining = $remaining - $dpTotal;
            }

            $tenor = $booking->installment_months > 0 ? (int)$booking->installment_months : 12;
            $perMonth = round($remaining / $tenor);

            for ($i = 1; $i <= $tenor; $i++) {
                $num = $offsetMonths + $i;
                $amount = ($i === $tenor) ? ($remaining - ($perMonth * ($tenor - 1))) : $perMonth;
                $booking->paymentSchedules()->create([
                    'installment_number' => $num,
                    'label' => "Cicilan Ke-$i (dari $tenor Bulan)",
                    'amount' => $amount,
                    'due_date' => $bookingDate->copy()->addMonths($num)->format('Y-m-d'),
                    'status' => 'upcoming',
                ]);
            }
        }
    }

    public function regenerateSchedule(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'payment_scheme' => 'required|in:cash,cash_installment,kpr',
            'installment_months' => 'nullable|integer|min:1|max:360',
            'dp_amount' => 'nullable|numeric|min:0',
            'dp_installment_months' => 'nullable|integer|min:0|max:60',
        ]);

        DB::transaction(function () use ($booking, $validated) {
            $booking->update([
                'payment_scheme' => $validated['payment_scheme'],
                'installment_months' => $validated['installment_months'] ?? 12,
                'dp_amount' => $validated['dp_amount'] ?? 0,
                'dp_installment_months' => $validated['dp_installment_months'] ?? 0,
            ]);

            // Keep paid items (like UTJ / paid installments)
            $booking->paymentSchedules()->where('status', '!=', 'paid')->where('installment_number', '!=', 0)->delete();

            $this->generateSchedules($booking);
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

            // Kembalikan status unit ke available
            $booking->unit->update(['status' => 'available', 'held_by' => null, 'held_until' => null]);

            AuditLog::record('booking_rejected', $booking, null, ['reason' => $request->reason]);

            return back()->with('success', 'Booking telah ditolak.');
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

            // Kembalikan status unit ke available
            $booking->unit->update(['status' => 'available', 'held_by' => null, 'held_until' => null]);
            
            // Kembalikan status lead ke negotiation (atau status sebelumnya)
            $booking->lead->update(['status' => 'negotiation']);

            AuditLog::record('booking_cancelled', $booking, null, ['reason' => $request->reason]);

            return back()->with('success', 'Booking telah dibatalkan.');
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
}
