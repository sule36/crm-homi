<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $currentYear = now()->year;
        $currentMonth = now()->month;

        $expenses = Expense::with(['category', 'project', 'recorder'])
            ->when($request->search, fn($q, $s) => $q->where('description', 'like', "%{$s}%")
                ->orWhere('vendor_name', 'like', "%{$s}%"))
            ->when($request->category_id, fn($q, $c) => $q->where('expense_category_id', $c))
            ->when($request->project_id, fn($q, $p) => $q->where('project_id', $p))
            ->when($request->month, function ($q, $m) {
                $q->whereMonth('expense_date', $m);
            })
            ->when($request->year, function ($q, $y) {
                $q->whereYear('expense_date', $y);
            })
            ->latest('expense_date')
            ->paginate(20)
            ->withQueryString();

        // Stats
        $totalThisMonth = Expense::where('status', 'approved')
            ->whereYear('expense_date', $currentYear)
            ->whereMonth('expense_date', $currentMonth)
            ->sum('amount');

        $totalThisYear = Expense::where('status', 'approved')
            ->whereYear('expense_date', $currentYear)
            ->sum('amount');

        // Per-category breakdown this month
        $categoryBreakdown = Expense::where('status', 'approved')
            ->whereYear('expense_date', $currentYear)
            ->whereMonth('expense_date', $currentMonth)
            ->select('expense_category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('expense_category_id')
            ->with('category')
            ->get()
            ->map(fn($e) => [
                'category' => $e->category->name,
                'icon' => $e->category->icon,
                'color' => $e->category->color,
                'total' => $e->total,
            ]);

        // Monthly trend (last 6 months)
        $monthlyTrend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $total = Expense::where('status', 'approved')
                ->whereYear('expense_date', $date->year)
                ->whereMonth('expense_date', $date->month)
                ->sum('amount');
            $monthlyTrend[] = [
                'month' => $date->translatedFormat('M Y'),
                'total' => (int) $total,
            ];
        }

        return Inertia::render('Finance/Expenses', [
            'expenses' => $expenses,
            'categories' => ExpenseCategory::active()->orderBy('sort_order')->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'rab_items' => \App\Models\RabItem::orderBy('category')->get(['id', 'project_id', 'category', 'description']),
            'bank_accounts' => \App\Models\BankAccount::where('is_active', true)->get(['id', 'name', 'current_balance']),
            'stats' => [
                'total_this_month' => (int) $totalThisMonth,
                'total_this_year' => (int) $totalThisYear,
                'category_breakdown' => $categoryBreakdown,
                'monthly_trend' => $monthlyTrend,
            ],
            'filters' => $request->only(['search', 'category_id', 'project_id', 'month', 'year']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'rab_item_id' => 'nullable|exists:rab_items,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:transfer,cash,cheque',
            'vendor_name' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'ppn_amount' => 'nullable|numeric|min:0',
            'pph_amount' => 'nullable|numeric|min:0',
        ]);

        $validated['recorded_by'] = auth()->id();
        $validated['status'] = 'approved';

        $expense = Expense::create($validated);

        AuditLog::record('expense_created', $expense, null, $expense->toArray());

        return back()->with('success', 'Pengeluaran berhasil dicatat.');
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'expense_category_id' => 'required|exists:expense_categories,id',
            'rab_item_id' => 'nullable|exists:rab_items,id',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:1',
            'expense_date' => 'required|date',
            'payment_method' => 'required|in:transfer,cash,cheque',
            'vendor_name' => 'nullable|string|max:255',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bank_account_id' => 'nullable|exists:bank_accounts,id',
            'ppn_amount' => 'nullable|numeric|min:0',
            'pph_amount' => 'nullable|numeric|min:0',
        ]);

        $oldData = $expense->toArray();

        DB::transaction(function () use ($expense, $validated) {
            // If rab_item_id was changed or removed, handle old RabRealization
            if ($expense->rab_item_id !== ($validated['rab_item_id'] ?? null)) {
                \App\Models\RabRealization::where('expense_id', $expense->id)->delete();
            }

            $expense->update($validated);

            // Update or create RabRealization
            if ($expense->rab_item_id) {
                \App\Models\RabRealization::updateOrCreate(
                    ['expense_id' => $expense->id],
                    [
                        'rab_item_id' => $expense->rab_item_id,
                        'amount' => $expense->amount,
                        'realization_date' => $expense->expense_date,
                        'vendor_name' => $expense->vendor_name,
                        'notes' => "[Pengeluaran] " . ($expense->notes ?? $expense->description),
                        'recorded_by' => auth()->id(),
                    ]
                );
            }

            // Update GL entry
            $gl = \App\Models\GeneralLedger::where('reference_type', Expense::class)
                ->where('reference_id', $expense->id)
                ->first();

            if ($gl) {
                $gl->update([
                    'debit' => $validated['amount'],
                    'description' => $validated['description'],
                    'date' => $validated['expense_date'],
                    'project_id' => $validated['project_id'] ?? null,
                    'bank_account_id' => $validated['bank_account_id'] ?? null,
                ]);
            }
        });

        AuditLog::record('expense_updated', $expense, $oldData, $expense->fresh()->toArray());

        return back()->with('success', 'Pengeluaran berhasil diperbarui.');
    }

    public function destroy(Expense $expense)
    {
        $oldData = $expense->toArray();

        AuditLog::record('expense_deleted', $expense, $oldData, null);

        $expense->delete(); // GL entry removed via model boot event

        return back()->with('success', 'Pengeluaran berhasil dihapus.');
    }
}
