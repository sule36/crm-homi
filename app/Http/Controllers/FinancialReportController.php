<?php

namespace App\Http\Controllers;

use App\Models\GeneralLedger;
use App\Models\Project;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class FinancialReportController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->year ?: now()->year;
        $projectId = $request->project_id ?: null;

        // ── Monthly Cash Flow (income vs expense per month) ──
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $query = GeneralLedger::whereYear('date', $year)->whereMonth('date', $m);
            if ($projectId) $query->where('project_id', $projectId);

            $income = (clone $query)->where('type', 'income')->sum('credit');
            $expense = (clone $query)->where('type', 'expense')->sum('debit');

            $months = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
            $monthlyData[] = [
                'month' => $months[$m],
                'month_num' => $m,
                'income' => (int) $income,
                'expense' => (int) $expense,
                'net' => (int) ($income - $expense),
            ];
        }

        // ── Profit/Loss by category ──
        $incomeQuery = GeneralLedger::where('type', 'income')->whereYear('date', $year);
        $expenseQuery = GeneralLedger::where('type', 'expense')->whereYear('date', $year);
        if ($projectId) {
            $incomeQuery->where('project_id', $projectId);
            $expenseQuery->where('project_id', $projectId);
        }

        $incomeByCategory = (clone $incomeQuery)
            ->select('category', DB::raw('SUM(credit) as total'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn($r) => [$r->category => (int) $r->total]);

        $expenseByCategory = (clone $expenseQuery)
            ->select('category', DB::raw('SUM(debit) as total'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn($r) => [$r->category => (int) $r->total]);

        $totalIncome = $incomeByCategory->sum();
        $totalExpense = $expenseByCategory->sum();

        // ── Per-Project Summary ──
        $projectSummaries = [];
        if (!$projectId) {
            $projects = Project::orderBy('name')->get();
            foreach ($projects as $proj) {
                $pIncome = GeneralLedger::where('type', 'income')
                    ->where('project_id', $proj->id)
                    ->whereYear('date', $year)
                    ->sum('credit');
                $pExpense = GeneralLedger::where('type', 'expense')
                    ->where('project_id', $proj->id)
                    ->whereYear('date', $year)
                    ->sum('debit');

                $projectSummaries[] = [
                    'id' => $proj->id,
                    'name' => $proj->name,
                    'income' => (int) $pIncome,
                    'expense' => (int) $pExpense,
                    'net' => (int) ($pIncome - $pExpense),
                ];
            }
        }

        // ── Tax Summaries (Pajak) ──
        $ppnKeluaran = \App\Models\Booking::whereYear('created_at', $year)
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->sum('ppn_amount');

        $ppnMasukan = \App\Models\Expense::whereYear('expense_date', $year)
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->sum('ppn_amount');

        $pphFinal = \App\Models\Booking::whereYear('created_at', $year)
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->get()
            ->sum(fn($b) => (int) (($b->booking_amount ?? $b->unit?->price ?? 0) * 0.025));

        $pphJasa = \App\Models\Expense::whereYear('expense_date', $year)
            ->when($projectId, fn($q, $p) => $q->where('project_id', $p))
            ->sum('pph_amount');

        // Category labels for display
        $categoryLabels = [
            'customer_payment' => '💰 Pembayaran Customer',
            'operational_expense' => '💸 Pengeluaran Operasional',
            'salary' => '💼 Gaji Karyawan',
            'commission' => '🤝 Komisi Agen',
            'rab_realization' => '🏗️ Realisasi RAB',
        ];

        return Inertia::render('Finance/Reports', [
            'monthly_data' => $monthlyData,
            'income_by_category' => $incomeByCategory,
            'expense_by_category' => $expenseByCategory,
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $totalIncome - $totalExpense,
            'project_summaries' => $projectSummaries,
            'category_labels' => $categoryLabels,
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'taxes' => [
                'ppn_keluaran' => (int) $ppnKeluaran,
                'ppn_masukan' => (int) $ppnMasukan,
                'ppn_net' => (int) ($ppnKeluaran - $ppnMasukan),
                'pph_final' => (int) $pphFinal,
                'pph_jasa' => (int) $pphJasa,
            ],
            'filters' => [
                'year' => (int) $year,
                'project_id' => $projectId ? (int) $projectId : null,
            ],
        ]);
    }

    public function export(Request $request)
    {
        $year = $request->year ?: now()->year;

        $entries = GeneralLedger::with('project')
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=laporan-keuangan-{$year}.csv",
        ];

        $callback = function () use ($entries) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['Tanggal', 'Tipe', 'Kategori', 'Deskripsi', 'Proyek', 'Debit', 'Kredit']);

            foreach ($entries as $e) {
                fputcsv($file, [
                    $e->date->format('Y-m-d'),
                    $e->type === 'income' ? 'Masuk' : 'Keluar',
                    $e->category,
                    $e->description,
                    $e->project?->name ?? 'Umum',
                    $e->debit,
                    $e->credit,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
