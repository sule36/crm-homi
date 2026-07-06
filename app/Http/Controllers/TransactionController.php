<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\PaymentSchedule;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'payment_schedule_id' => 'required|exists:payment_schedules,id',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:transfer,cash,cheque',
            'bank_name' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $transaction = Transaction::create([
                ...$validated,
                'recorded_by' => auth()->id(),
            ]);

            // Update Payment Schedule Status
            $schedule = PaymentSchedule::find($validated['payment_schedule_id']);
            $totalPaidForSchedule = Transaction::where('payment_schedule_id', $schedule->id)->sum('amount');

            if ($totalPaidForSchedule >= $schedule->amount) {
                $schedule->update(['status' => 'paid']);
            }

            AuditLog::record('payment_recorded', $transaction, null, $transaction->toArray());

            return back()->with('success', 'Pembayaran berhasil dicatat.');
        });
    }

    public function destroy(Transaction $transaction)
    {
        return DB::transaction(function () use ($transaction) {
            $scheduleId = $transaction->payment_schedule_id;
            $oldData = $transaction->toArray();
            $transaction->delete();

            // Re-check schedule status
            $schedule = PaymentSchedule::find($scheduleId);
            $totalPaidForSchedule = Transaction::where('payment_schedule_id', $schedule->id)->sum('amount');

            if ($totalPaidForSchedule < $schedule->amount) {
                // If it was already past due, keep it due. If it's in the future, it's upcoming.
                $newStatus = (new \DateTime($schedule->due_date) < new \DateTime()) ? 'due' : 'upcoming';
                $schedule->update(['status' => $newStatus]);
            }

            AuditLog::record('payment_deleted', $transaction, $oldData, null);

            return back()->with('success', 'Transaksi berhasil dihapus.');
        });
    }

    public function receipt(Transaction $transaction)
    {
        $transaction->load(['booking.lead', 'booking.unit.project', 'recordedBy', 'bankAccount']);
        
        $spelledText = ucwords(trim($this->terbilang($transaction->amount))) . " Rupiah";

        return Inertia::render('Finance/Receipt', [
            'transaction' => $transaction,
            'spelled_text' => $spelledText,
        ]);
    }

    private function terbilang($angka)
    {
        $angka = abs($angka);
        $baca = ["", "satu", "dua", "tiga", "empat", "lima", "enam", "tujuh", "delapan", "sembilan", "sepuluh", "sebelas"];
        $temp = "";
        if ($angka < 12) {
            $temp = " " . $baca[$angka];
        } else if ($angka < 20) {
            $temp = $this->terbilang($angka - 10) . " belas";
        } else if ($angka < 100) {
            $temp = $this->terbilang((int)($angka / 10)) . " puluh" . $this->terbilang($angka % 10);
        } else if ($angka < 200) {
            $temp = " seratus" . $this->terbilang($angka - 100);
        } else if ($angka < 1000) {
            $temp = $this->terbilang((int)($angka / 100)) . " ratus" . $this->terbilang($angka % 100);
        } else if ($angka < 2000) {
            $temp = " seribu" . $this->terbilang($angka - 1000);
        } else if ($angka < 1000000) {
            $temp = $this->terbilang((int)($angka / 1000)) . " ribu" . $this->terbilang($angka % 1000);
        } else if ($angka < 1000000000) {
            $temp = $this->terbilang((int)($angka / 1000000)) . " juta" . $this->terbilang($angka % 1000000);
        } else if ($angka < 1000000000000) {
            $temp = $this->terbilang((int)($angka / 1000000000)) . " milyar" . $this->terbilang(fmod($angka, 1000000000));
        }
        return $temp;
    }
}
