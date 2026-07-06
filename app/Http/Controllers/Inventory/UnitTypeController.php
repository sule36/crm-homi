<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\UnitType;
use App\Models\Project;
use Illuminate\Http\Request;

class UnitTypeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'building_area' => 'required|numeric',
            'land_area' => 'required|numeric',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'current_price' => 'required|numeric',
        ]);

        UnitType::create($validated);

        return back()->with('success', 'Tipe unit berhasil ditambahkan.');
    }

    public function update(Request $request, UnitType $unitType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'building_area' => 'required|numeric',
            'land_area' => 'required|numeric',
            'bedrooms' => 'nullable|integer',
            'bathrooms' => 'nullable|integer',
            'current_price' => 'required|numeric',
        ]);

        $unitType->update($validated);

        return back()->with('success', 'Tipe unit diperbarui.');
    }

    public function destroy(UnitType $unitType)
    {
        if ($unitType->units()->count() > 0) {
            return back()->with('error', 'Tipe unit tidak dapat dihapus karena masih memiliki unit.');
        }

        $unitType->delete();
        return back()->with('success', 'Tipe unit dihapus.');
    }
}
