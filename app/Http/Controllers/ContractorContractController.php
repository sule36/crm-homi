<?php

namespace App\Http\Controllers;

use App\Models\ContractorContract;
use App\Models\ContractorTermin;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\RabRealization;
use App\Models\BankAccount;
use App\Models\Project;
use App\Models\RabItem;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ContractorContractController extends Controller
{
    public function index(Request $request)
    {
        $projectId = $request->project_id;
        $projects = Project::orderBy('name')->get(['id', 'name']);

        $contracts = collect();
        $rabItems = collect();

        if ($projectId) {
            $contracts = ContractorContract::where('project_id', $projectId)
                ->with(['termins.expense', 'termins.rabItem'])
                ->latest()
                ->get()
                ->map(function ($contract) {
                    $contract->paid_amount = $contract->paid_amount;
                    $contract->remaining_amount = $contract->remaining_amount;
                    return $contract;
                });

            $rabItems = RabItem::where('project_id', $projectId)
                ->orderBy('category')
                ->get();
        }

        $bankAccounts = BankAccount::where('is_active', true)->get(['id', 'name', 'bank_name', 'account_number', 'current_balance']);

        return Inertia::render('Finance/Contracts', [
            'contracts' => $contracts,
            'projects' => $projects,
            'rab_items' => $rabItems,
            'bank_accounts' => $bankAccounts,
            'filters' => ['project_id' => $projectId ? (int) $projectId : null],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'contractor_name' => 'required|string|max:255',
            'contract_number' => 'required|string|max:100|unique:contractor_contracts',
            'description' => 'required|string|max:255',
            'total_amount' => 'required|numeric|min:0',
            'termins' => 'required|array|min:1',
            'termins.*.label' => 'required|string|max:100',
            'termins.*.percentage' => 'required|numeric|between:0.01,100',
            'termins.*.amount' => 'required|numeric|min:0',
            'termins.*.due_date' => 'nullable|date',
            'termins.*.rab_item_id' => 'nullable|exists:rab_items,id',
        ]);

        return DB::transaction(function () use ($validated) {
            $contract = ContractorContract::create([
                'project_id' => $validated['project_id'],
                'contractor_name' => $validated['contractor_name'],
                'contract_number' => $validated['contract_number'],
                'description' => $validated['description'],
                'total_amount' => $validated['total_amount'],
                'status' => 'active',
            ]);

            foreach ($validated['termins'] as $t) {
                ContractorTermin::create([
                    'contractor_contract_id' => $contract->id,
                    'rab_item_id' => $t['rab_item_id'] ?? null,
                    'label' => $t['label'],
                    'percentage' => $t['percentage'],
                    'amount' => $t['amount'],
                    'status' => 'pending',
                    'due_date' => $t['due_date'] ?? null,
                ]);
            }

            AuditLog::record('contractor_contract_created', $contract, null, $contract->load('termins')->toArray());

            return back()->with('success', 'Kontrak subkontraktor berhasil dibuat.');
        });
    }

    public function update(Request $request, ContractorContract $contract)
    {
        $validated = $request->validate([
            'contractor_name' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'status' => 'required|in:active,completed',
        ]);

        $oldData = $contract->toArray();
        $contract->update($validated);

        AuditLog::record('contractor_contract_updated', $contract, $oldData, $contract->fresh()->toArray());

        return back()->with('success', 'Kontrak subkontraktor berhasil diperbarui.');
    }

    public function destroy(ContractorContract $contract)
    {
        $oldData = $contract->toArray();
        
        DB::transaction(function () use ($contract) {
            // Delete associated unpaid termins
            $contract->termins()->delete();
            $contract->delete();
        });

        AuditLog::record('contractor_contract_deleted', null, $oldData);

        return back()->with('success', 'Kontrak subkontraktor berhasil dihapus.');
    }

    /**
     * Pay a subcontractor termin.
     */
    public function payTermin(Request $request, ContractorTermin $termin)
    {
        if ($termin->status === 'paid') {
            return back()->with('error', 'Termin ini sudah dibayarkan.');
        }

        $validated = $request->validate([
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'paid_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($termin, $validated) {
            $contract = $termin->contract;
            
            // Find Subcontractor category
            $subCategory = ExpenseCategory::where('code', 'SUB')->first()
                ?? ExpenseCategory::first();

            // 1. Create linked Expense
            $expense = Expense::create([
                'project_id' => $contract->project_id,
                'expense_category_id' => $subCategory->id,
                'description' => "[SUBKON] {$contract->contractor_name} - {$termin->label} (Kontrak: {$contract->contract_number})",
                'amount' => $termin->amount,
                'expense_date' => $validated['paid_date'],
                'payment_method' => 'transfer',
                'vendor_name' => $contract->contractor_name,
                'receipt_number' => "TERM-{$termin->id}",
                'notes' => $validated['notes'],
                'recorded_by' => auth()->id(),
                'bank_account_id' => $validated['bank_account_id'],
                'status' => 'approved', // This automatically creates GeneralLedger entry and recalculates bank balance!
            ]);

            // 2. Create RAB Realization if rab_item_id is set
            if ($termin->rab_item_id) {
                RabRealization::create([
                    'rab_item_id' => $termin->rab_item_id,
                    'expense_id' => $expense->id,
                    'amount' => $termin->amount,
                    'realization_date' => $validated['paid_date'],
                    'vendor_name' => $contract->contractor_name,
                    'notes' => "[Termin Subkon] " . $validated['notes'],
                    'recorded_by' => auth()->id(),
                ]);
            }

            // 3. Update Termin status
            $termin->update([
                'status' => 'paid',
                'paid_date' => $validated['paid_date'],
                'expense_id' => $expense->id,
            ]);

            // Optional: If all termins are paid, complete the contract
            $allPaid = ContractorTermin::where('contractor_contract_id', $contract->id)
                ->where('status', 'pending')
                ->count() === 0;

            if ($allPaid) {
                $contract->update(['status' => 'completed']);
            }

            AuditLog::record('contractor_termin_paid', $termin, null, $termin->toArray());

            return back()->with('success', "Termin {$termin->label} berhasil dibayarkan.");
        });
    }
}
