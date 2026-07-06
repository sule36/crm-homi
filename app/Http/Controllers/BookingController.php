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
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $unit = Unit::findOrFail($validated['unit_id']);
            $agent = \App\Models\User::findOrFail($validated['booked_by']);
            $agent->load('brokerCompany');
            $rate = $agent->brokerCompany ? $agent->brokerCompany->commission_rate : ($agent->commission_rate ?? 1);
            $commissionAmount = $validated['final_price'] * ($rate / 100);
            
            // 1. Create Booking
            $booking = Booking::create([
                'spk_number' => Booking::generateSpkNumber(),
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
                'booking_date' => now(),
                'status' => 'pending',
                'notes' => $validated['notes'],
                'commission_amount' => $commissionAmount,
            ]);

            // 2. Update Unit Status to 'hold'
            $unit->update(['status' => 'hold', 'held_by' => $validated['booked_by'], 'held_until' => now()->addDays(2)]);

            AuditLog::record('booking_created', $booking, null, $booking->toArray());

            return redirect()->route('bookings.index')->with('success', 'Booking berhasil diajukan.');
        });
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'lead', 'unit.unitType', 'project', 'bookedBy', 'approvedBy',
            'paymentSchedules', 'transactions', 'documents'
        ]);
        return Inertia::render('Bookings/Show', [
            'booking' => $booking
        ]);
    }

    public function approve(Booking $booking)
    {
        return DB::transaction(function () use ($booking) {
            $booking->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
            ]);

            // Generate Commission (null-safe rate, default 1%)
            $agent = $booking->bookedBy;
            $agent->load('brokerCompany');
            $rate = $agent->brokerCompany ? $agent->brokerCompany->commission_rate : ($agent->commission_rate ?? 1);
            $commissionAmount = $booking->base_price * ($rate / 100);
            
            \App\Models\Commission::create([
                'user_id' => $agent->id,
                'booking_id' => $booking->id,
                'amount' => $commissionAmount,
                'status' => 'pending',
                'notes' => "Komisi {$rate}% dari Booking #{$booking->spk_number}",
            ]);

            // Update commission on booking record
            $booking->update(['commission_amount' => $commissionAmount]);

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

        // 1. Booking Fee (UTJ) - Installment 0
        $schedule = $booking->paymentSchedules()->create([
            'installment_number' => 0,
            'label' => 'Booking Fee (UTJ)',
            'amount' => $booking->booking_fee,
            'due_date' => $booking->booking_date,
            'status' => 'paid',
        ]);

        // RECORD TRANSACTION FOR UTJ
        \App\Models\Transaction::create([
            'booking_id' => $booking->id,
            'payment_schedule_id' => $schedule->id,
            'amount' => $booking->booking_fee,
            'payment_method' => 'cash',
            'notes' => 'Otomatis dari Booking Fee',
            'recorded_by' => auth()->id() ?? $booking->booked_by,
        ]);

        // 2. Taxes & Legal (separate item, due within 30 days)
        if ($taxLegalTotal > 0) {
            $booking->paymentSchedules()->create([
                'installment_number' => 99,
                'label' => 'Pajak & Biaya Legal (PPN, BPHTB, AJB)',
                'amount' => $taxLegalTotal,
                'due_date' => now()->addDays(30),
                'status' => 'upcoming',
            ]);
        }

        // 3. Unit price installments (using base_price minus UTJ)
        $remaining = $basePrice - $booking->booking_fee;

        if ($booking->payment_scheme === 'kpr') {
            // DP (10% of base_price, split into 3 months)
            $dpTotal = $basePrice * 0.10;
            $dpPerMonth = round($dpTotal / 3);
            for ($i = 1; $i <= 3; $i++) {
                $booking->paymentSchedules()->create([
                    'installment_number' => $i,
                    'label' => "DP Ke-$i",
                    'amount' => $dpPerMonth,
                    'due_date' => now()->addMonths($i),
                    'status' => 'upcoming',
                ]);
            }
            // Bank Liquidation (remaining after UTJ and DP)
            $bankAmount = $basePrice - $booking->booking_fee - $dpTotal;
            $booking->paymentSchedules()->create([
                'installment_number' => 4,
                'label' => 'Pencairan KPR (Bank)',
                'amount' => max(0, $bankAmount),
                'due_date' => now()->addMonths(4),
                'status' => 'upcoming',
            ]);
        } elseif ($booking->payment_scheme === 'cash') {
            // Single Pelunasan
            $booking->paymentSchedules()->create([
                'installment_number' => 1,
                'label' => 'Pelunasan Cash Keras',
                'amount' => max(0, $remaining),
                'due_date' => now()->addDays(14),
                'status' => 'upcoming',
            ]);
        } else {
            // Cash Installment (12 months)
            $perMonth = round($remaining / 12);
            for ($i = 1; $i <= 12; $i++) {
                // Last installment gets the remainder to avoid rounding errors
                $amount = ($i === 12) ? ($remaining - ($perMonth * 11)) : $perMonth;
                $booking->paymentSchedules()->create([
                    'installment_number' => $i,
                    'label' => "Cicilan Ke-$i",
                    'amount' => $amount,
                    'due_date' => now()->addMonths($i),
                    'status' => 'upcoming',
                ]);
            }
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
