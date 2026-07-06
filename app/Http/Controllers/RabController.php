<?php

namespace App\Http\Controllers;

use App\Models\RabItem;
use App\Models\RabRealization;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class RabController extends Controller
{
    public function index(Request $request)
    {
        $projectId = $request->project_id;
        $projects = Project::orderBy('name')->get(['id', 'name']);

        $items = collect();
        $summary = null;

        if ($projectId) {
            $items = RabItem::where('project_id', $projectId)
                ->withSum('realizations', 'amount')
                ->orderBy('category')
                ->orderBy('sort_order')
                ->get()
                ->map(function ($item) {
                    $item->realization_total = (int) ($item->realizations_sum_amount ?? 0);
                    $item->deviation = $item->total_price - $item->realization_total;
                    $item->realization_percent = $item->total_price > 0
                        ? round(($item->realization_total / $item->total_price) * 100, 1)
                        : 0;
                    return $item;
                });

            $totalBudget = $items->sum('total_price');
            $totalRealization = $items->sum('realization_total');

            // Group by category summary
            $categoryGroups = $items->groupBy('category')->map(function ($group, $cat) {
                return [
                    'category' => $cat,
                    'label' => RabItem::categoryLabels()[$cat] ?? $cat,
                    'total_budget' => $group->sum('total_price'),
                    'total_realization' => $group->sum('realization_total'),
                    'item_count' => $group->count(),
                ];
            })->values();

            $summary = [
                'total_budget' => (int) $totalBudget,
                'total_realization' => (int) $totalRealization,
                'deviation' => (int) ($totalBudget - $totalRealization),
                'progress_percent' => $totalBudget > 0 ? round(($totalRealization / $totalBudget) * 100, 1) : 0,
                'category_groups' => $categoryGroups,
            ];
        }

        return Inertia::render('Finance/RAB', [
            'items' => $items,
            'projects' => $projects,
            'summary' => $summary,
            'category_labels' => RabItem::categoryLabels(),
            'filters' => ['project_id' => $projectId ? (int) $projectId : null],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'category' => 'required|string',
            'sub_category' => 'nullable|string',
            'description' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'volume' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $item = RabItem::create($validated);

        AuditLog::record('rab_item_created', $item, null, $item->toArray());

        return back()->with('success', 'Item RAB berhasil ditambahkan.');
    }

    public function update(Request $request, RabItem $rabItem)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'sub_category' => 'nullable|string',
            'description' => 'required|string|max:255',
            'unit' => 'required|string|max:20',
            'volume' => 'required|numeric|min:0',
            'unit_price' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $old = $rabItem->toArray();
        $rabItem->update($validated);

        AuditLog::record('rab_item_updated', $rabItem, $old, $rabItem->fresh()->toArray());

        return back()->with('success', 'Item RAB berhasil diperbarui.');
    }

    public function destroy(RabItem $rabItem)
    {
        $old = $rabItem->toArray();
        $rabItem->delete();

        AuditLog::record('rab_item_deleted', null, $old);

        return back()->with('success', 'Item RAB berhasil dihapus.');
    }

    /**
     * Record a realization against an RAB item.
     * Optionally creates a linked Expense record.
     */
    public function realize(Request $request, RabItem $rabItem)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'realization_date' => 'required|date',
            'vendor_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'create_expense' => 'nullable|boolean',
        ]);

        return DB::transaction(function () use ($validated, $rabItem, $request) {
            $expenseId = null;

            // Optionally create a linked expense
            if ($request->boolean('create_expense')) {
                // Find or use a default "Material Bangunan" category
                $cat = ExpenseCategory::where('code', 'MAT')->first()
                    ?? ExpenseCategory::first();

                $notes = $validated['notes'] ?? 'Realisasi';

                $expense = Expense::create([
                    'project_id' => $rabItem->project_id,
                    'expense_category_id' => $cat->id,
                    'description' => "[RAB] {$rabItem->description} - {$notes}",
                    'amount' => $validated['amount'],
                    'expense_date' => $validated['realization_date'],
                    'payment_method' => 'transfer',
                    'vendor_name' => $validated['vendor_name'],
                    'recorded_by' => auth()->id(),
                    'status' => 'approved',
                ]);

                $expenseId = $expense->id;
            }

            $realization = RabRealization::create([
                'rab_item_id' => $rabItem->id,
                'expense_id' => $expenseId,
                'amount' => $validated['amount'],
                'realization_date' => $validated['realization_date'],
                'vendor_name' => $validated['vendor_name'],
                'notes' => $validated['notes'],
                'recorded_by' => auth()->id(),
            ]);

            AuditLog::record('rab_realization_created', $realization, null, $realization->toArray());

            return back()->with('success', 'Realisasi RAB berhasil dicatat.');
        });
    }

    /**
     * Duplicate RAB items from one project to another.
     */
    public function duplicate(Request $request)
    {
        $request->validate([
            'source_project_id' => 'required|exists:projects,id',
            'target_project_id' => 'required|exists:projects,id|different:source_project_id',
        ]);

        $sourceItems = RabItem::where('project_id', $request->source_project_id)->get();

        if ($sourceItems->isEmpty()) {
            return back()->with('error', 'Proyek sumber tidak memiliki item RAB.');
        }

        DB::transaction(function () use ($sourceItems, $request) {
            foreach ($sourceItems as $item) {
                RabItem::create([
                    'project_id' => $request->target_project_id,
                    'category' => $item->category,
                    'sub_category' => $item->sub_category,
                    'description' => $item->description,
                    'unit' => $item->unit,
                    'volume' => $item->volume,
                    'unit_price' => $item->unit_price,
                    'notes' => $item->notes,
                    'sort_order' => $item->sort_order,
                ]);
            }
        });

        return back()->with('success', "Berhasil menduplikasi {$sourceItems->count()} item RAB.");
    }

    /**
     * Export RAB to CSV.
     */
    public function exportCsv(Request $request)
    {
        $projectId = $request->project_id;
        if (!$projectId) {
            return back()->with('error', 'Pilih proyek terlebih dahulu.');
        }

        $project = Project::findOrFail($projectId);
        $items = RabItem::where('project_id', $projectId)
            ->withSum('realizations', 'amount')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get();

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=RAB-{$project->name}-" . date('Y-m-d') . ".csv",
            "Pragma" => "no-cache",
        ];

        $callback = function () use ($items) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($file, ['Kategori', 'Uraian Pekerjaan', 'Satuan', 'Volume', 'Harga Satuan', 'Total Anggaran', 'Realisasi', 'Selisih']);

            foreach ($items as $item) {
                $realization = (int) ($item->realizations_sum_amount ?? 0);
                fputcsv($file, [
                    RabItem::categoryLabels()[$item->category] ?? $item->category,
                    $item->description,
                    $item->unit,
                    $item->volume,
                    $item->unit_price,
                    $item->total_price,
                    $realization,
                    $item->total_price - $realization,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
