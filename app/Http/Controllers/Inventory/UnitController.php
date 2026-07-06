<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\Project;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::with(['project', 'unitType', 'heldByUser', 'latestProgress', 'progressHistory.recordedBy'])
            ->when($request->search, fn ($q, $s) => $q->where('block', 'like', "%{$s}%")->orWhere('number', 'like', "%{$s}%"))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->unit_type_id, fn ($q, $t) => $q->where('unit_type_id', $t))
            ->orderBy('block')->orderBy('number')
            ->paginate(50)
            ->withQueryString();

        // Stats per project
        $stats = Unit::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        // Master Plan data (grouped by block then floor)
        $masterPlanProject = $request->project_id ?? Project::first()?->id;
        $masterPlan = $masterPlanProject ? Unit::where('project_id', $masterPlanProject)
            ->with(['latestProgress', 'project', 'unitType'])
            ->orderBy('number')
            ->get()
            ->groupBy(['block', 'floor']) : [];

        return Inertia::render('Units/Index', [
            'units' => $units,
            'stats' => $stats,
            'masterPlan' => $masterPlan,
            'filters' => $request->only(['search', 'project_id', 'status', 'unit_type_id']),
            'projects' => Project::select('id', 'name', 'code')->get(),
            'unitTypes' => UnitType::select('id', 'name', 'project_id', 'current_price')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'unit_type_id' => 'required|exists:unit_types,id',
            'block' => 'nullable|string|max:10',
            'number' => 'required|string|max:10',
            'floor' => 'nullable|integer',
            'facing_direction' => 'nullable|string|max:20',
            'premium_charge' => 'nullable|numeric|min:0',
            'final_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'certificate_status' => 'nullable|in:belum_pecah,pecah_di_notaris,sudah_balik_nama,diserahkan_ke_konsumen,diserahkan_ke_bank',
            'certificate_number' => 'nullable|string|max:255',
            'imb_number' => 'nullable|string|max:255',
            'pbb_number' => 'nullable|string|max:255',
            'legal_notes' => 'nullable|string',
        ]);

        $type = UnitType::find($validated['unit_type_id']);
        $validated['status'] = 'available';

        // Auto calculate premium if not set
        if (empty($validated['premium_charge']) && !empty($validated['facing_direction'])) {
            $validated['premium_charge'] = $this->calculatePremium($type->current_price, $validated['facing_direction']);
        }

        if (empty($validated['final_price'])) {
            $validated['final_price'] = $type->current_price + ($validated['premium_charge'] ?? 0);
        }
        $unit = Unit::create($validated);

        // Recalculate project counts
        $unit->project->recalculateUnits();

        AuditLog::record('created', $unit, null, $validated);
        return back()->with('success', 'Unit berhasil ditambahkan.');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:available,hold,booked,sold,cancelled',
            'facing_direction' => 'nullable|string|max:20',
            'premium_charge' => 'nullable|numeric|min:0',
            'final_price' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'certificate_status' => 'nullable|in:belum_pecah,pecah_di_notaris,sudah_balik_nama,diserahkan_ke_konsumen,diserahkan_ke_bank',
            'certificate_number' => 'nullable|string|max:255',
            'imb_number' => 'nullable|string|max:255',
            'pbb_number' => 'nullable|string|max:255',
            'legal_notes' => 'nullable|string',
        ]);

        $old = $unit->toArray();
        $unit->update($validated);
        $unit->project->recalculateUnits();

        AuditLog::record('updated', $unit, $old, $validated);
        return back()->with('success', 'Unit berhasil diperbarui.');
    }

    public function destroy(Unit $unit)
    {
        if ($unit->booking()->count() > 0 || \App\Models\Booking::where('unit_id', $unit->id)->count() > 0) {
            return back()->with('error', 'Unit tidak dapat dihapus karena sudah memiliki booking.');
        }

        AuditLog::record('deleted', $unit, $unit->toArray());
        $projectId = $unit->project_id;
        $unit->delete();
        Project::find($projectId)?->recalculateUnits();
        return back()->with('success', 'Unit berhasil dihapus.');
    }

    // Bulk create units
    public function bulkStore(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'unit_type_id' => 'required|exists:unit_types,id',
            'block' => 'required|string|max:10',
            'start_number' => 'required|integer|min:1',
            'end_number' => 'required|integer|gte:start_number',
            'facing_direction' => 'nullable|string',
        ]);

        $type = UnitType::find($validated['unit_type_id']);
        $created = 0;

        for ($i = $validated['start_number']; $i <= $validated['end_number']; $i++) {
            $premium = $this->calculatePremium($type->current_price, $validated['facing_direction'] ?? '');
            
            Unit::firstOrCreate(
                ['project_id' => $validated['project_id'], 'block' => $validated['block'], 'number' => str_pad($i, 2, '0', STR_PAD_LEFT)],
                [
                    'unit_type_id' => $validated['unit_type_id'],
                    'status' => 'available',
                    'facing_direction' => $validated['facing_direction'],
                    'premium_charge' => $premium,
                    'final_price' => $type->current_price + $premium,
                ]
            );
            $created++;
        }

        Project::find($validated['project_id'])->recalculateUnits();

        return back()->with('success', "{$created} unit berhasil dibuat.");
    }

    private function calculatePremium($basePrice, $facingDirection): float
    {
        $direction = strtolower($facingDirection);
        if (str_contains($direction, 'taman') || str_contains($direction, 'park')) {
            return $basePrice * 0.05; // 5% premium
        }
        if (str_contains($direction, 'timur') || str_contains($direction, 'east')) {
            return $basePrice * 0.02; // 2% premium
        }
        if (str_contains($direction, 'corner') || str_contains($direction, 'hook')) {
            return $basePrice * 0.10; // 10% premium
        }
        return 0;
    }
}
