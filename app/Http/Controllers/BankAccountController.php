<?php

namespace App\Http\Controllers;

use App\Models\BankAccount;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'account_holder' => 'nullable|string|max:100',
            'initial_balance' => 'required|numeric|min:0',
        ]);

        $validated['current_balance'] = $validated['initial_balance'];

        $bankAccount = BankAccount::create($validated);

        AuditLog::record('bank_account_created', $bankAccount, null, $bankAccount->toArray());

        return back()->with('success', 'Rekening bank berhasil ditambahkan.');
    }

    public function update(Request $request, BankAccount $bankAccount)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'bank_name' => 'nullable|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'account_holder' => 'nullable|string|max:100',
            'initial_balance' => 'required|numeric|min:0',
        ]);

        $oldData = $bankAccount->toArray();
        
        $bankAccount->update($validated);
        $bankAccount->syncBalance(); // Recalculate with ledger changes

        AuditLog::record('bank_account_updated', $bankAccount, $oldData, $bankAccount->fresh()->toArray());

        return back()->with('success', 'Rekening bank berhasil diperbarui.');
    }

    public function destroy(BankAccount $bankAccount)
    {
        $oldData = $bankAccount->toArray();
        
        AuditLog::record('bank_account_deleted', $bankAccount, $oldData, null);
        
        $bankAccount->delete();

        return back()->with('success', 'Rekening bank berhasil dihapus.');
    }
}
