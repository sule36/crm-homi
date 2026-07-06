<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Booking;
use App\Models\PaymentSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $transactions = Transaction::with(['booking.lead', 'booking.unit', 'recordedBy'])
            ->latest()
            ->paginate(15);

        $isSqlite = DB::getDriverName() === 'sqlite';
        $dateFormat = $isSqlite ? "strftime('%Y-%m', due_date)" : "DATE_FORMAT(due_date, '%Y-%m')";

        // Proyeksi Uang Masuk (Cicilan yang belum dibayar di masa depan)
        $projections = PaymentSchedule::whereIn('status', ['due', 'upcoming'])
            ->selectRaw("{$dateFormat} as month, SUM(amount) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Piutang Jatuh Tempo (Overdue)
        $overdue = PaymentSchedule::where('status', 'due')
            ->where('due_date', '<', now())
            ->sum('amount');

        // Optimized: Only sum current year instead of ALL transactions
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $overdueSchedules = PaymentSchedule::with(['booking.lead', 'booking.unit.project'])
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->orderBy('due_date', 'asc')
            ->get();

        return Inertia::render('Finance/Index', [
            'transactions' => $transactions,
            'overdue_schedules' => $overdueSchedules,
            'bank_accounts' => \App\Models\BankAccount::where('is_active', true)->get(['id', 'name', 'current_balance']),
            'stats' => [
                'total_revenue' => Transaction::whereYear('created_at', $currentYear)->sum('amount'),
                'overdue_payments' => $overdue,
                'monthly_revenue' => Transaction::whereYear('created_at', $currentYear)
                    ->whereMonth('created_at', $currentMonth)
                    ->sum('amount'),
                'projections' => $projections,
            ]
        ]);
    }

    public function exportCsv()
    {
        $transactions = Transaction::with(['booking.lead', 'booking.unit.project', 'recordedBy'])
            ->latest()
            ->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=laporan-keuangan-" . date('Y-m-d') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($transactions) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, ['Tanggal', 'No. Booking', 'Nama Konsumen', 'Unit Kavling', 'Proyek', 'Metode Pembayaran', 'Bank/Referensi', 'Jumlah', 'Catatan', 'Dicatat Oleh']);

            foreach ($transactions as $tx) {
                fputcsv($file, [
                    $tx->created_at->format('Y-m-d H:i'),
                    $tx->booking?->spk_number ?? $tx->booking_id,
                    $tx->booking?->lead?->name ?? '-',
                    $tx->booking?->unit ? ('Blok ' . $tx->booking->unit->block . ' No. ' . $tx->booking->unit->number) : '-',
                    $tx->booking?->unit?->project?->name ?? '-',
                    strtoupper($tx->payment_method),
                    $tx->bank_name . ($tx->reference_number ? ' / ' . $tx->reference_number : ''),
                    $tx->amount,
                    $tx->notes ?? '-',
                    $tx->recordedBy?->name ?? 'System'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function create()
    {
        return Inertia::render('Finance/Create', [
            'bookings' => Booking::with(['lead', 'unit'])->where('status', 'approved')->get(),
            'bank_accounts' => \App\Models\BankAccount::where('is_active', true)->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'amount' => 'required|numeric|min:0',
            'payment_method' => 'required|string',
            'bank_name' => 'nullable|string',
            'reference_number' => 'nullable|string',
            'notes' => 'nullable|string',
            'payment_schedule_id' => 'nullable|exists:payment_schedules,id',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $validated['recorded_by'] = auth()->id();
            $transaction = Transaction::create($validated);

            // If linked to payment schedule, check if total payments meet the schedule amount
            if (!empty($validated['payment_schedule_id'])) {
                $schedule = PaymentSchedule::find($validated['payment_schedule_id']);
                $totalPaid = Transaction::where('payment_schedule_id', $schedule->id)->sum('amount');

                if ($totalPaid >= $schedule->amount) {
                    $schedule->update(['status' => 'paid']);
                }
            }

            \App\Models\AuditLog::record('payment_recorded', $transaction, null, $transaction->toArray());

            return redirect()->route('finance.index')->with('success', 'Pembayaran berhasil dicatat.');
        });
    }
}
