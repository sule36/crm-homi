<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitProgress;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class UnitProgressController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'progress_percentage' => 'required|integer|between:0,100',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'recorded_date' => 'required|date',
        ]);

        $validated['recorded_by'] = auth()->id();

        $progress = UnitProgress::create($validated);

        // Record audit log
        AuditLog::record('unit_progress_created', $progress, null, $progress->toArray());

        return back()->with('success', 'Progress fisik unit berhasil dicatat.');
    }
}
