<?php

namespace App\Http\Controllers;

use App\Models\BrokerCompany;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BrokerCompanyController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $broker = BrokerCompany::create($validated);

        AuditLog::record('broker_created', $broker, null, $broker->toArray());

        return back()->with('success', 'Kantor agen berhasil ditambahkan.');
    }

    public function update(Request $request, BrokerCompany $broker)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'required|in:active,inactive',
        ]);

        $oldValues = $broker->toArray();
        $broker->update($validated);

        AuditLog::record('broker_updated', $broker, $oldValues, $broker->toArray());

        return back()->with('success', 'Kantor agen berhasil diperbarui.');
    }

    public function destroy(BrokerCompany $broker)
    {
        $oldValues = $broker->toArray();

        AuditLog::record('broker_deleted', $broker, $oldValues, null);

        $broker->delete();

        return back()->with('success', 'Kantor agen berhasil dihapus.');
    }
}
