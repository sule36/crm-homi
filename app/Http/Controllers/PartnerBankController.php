<?php

namespace App\Http\Controllers;

use App\Models\PartnerBank;
use Illuminate\Http\Request;

class PartnerBankController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'interest_rate_fixed' => 'required|numeric|min:0|max:100',
            'interest_rate_floating' => 'required|numeric|min:0|max:100',
            'fixed_duration' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'is_syariah' => 'boolean',
            'syariah_margin_rate' => 'required|numeric|min:0|max:100',
            'is_tiered' => 'boolean',
            'tiered_rates' => 'nullable|array',
        ]);

        PartnerBank::create($validated);

        return back()->with('success', 'Bank partner berhasil ditambahkan.');
    }

    public function update(Request $request, PartnerBank $partnerBank)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|string',
            'interest_rate_fixed' => 'required|numeric|min:0|max:100',
            'interest_rate_floating' => 'required|numeric|min:0|max:100',
            'fixed_duration' => 'required|integer|min:0|max:100',
            'is_active' => 'boolean',
            'is_syariah' => 'boolean',
            'syariah_margin_rate' => 'required|numeric|min:0|max:100',
            'is_tiered' => 'boolean',
            'tiered_rates' => 'nullable|array',
        ]);

        $partnerBank->update($validated);

        return back()->with('success', 'Bank partner berhasil diperbarui.');
    }

    public function destroy(PartnerBank $partnerBank)
    {
        $partnerBank->delete();

        return back()->with('success', 'Bank partner berhasil dihapus.');
    }
}
