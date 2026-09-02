<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\BrokerCompany;
use App\Models\Commission;
use App\Models\Booking;
use App\Models\AuditLog;
use App\Models\MasterLeadInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class MasterLeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        // Auto-heal / sync missing Master Lead overriding commissions for all approved bookings
        $approvedBookings = Booking::with(['bookedBy.brokerCompany', 'bookedBy.masterLead'])
            ->whereIn('status', ['approved', 'completed', 'booked'])
            ->get();

        foreach ($approvedBookings as $bk) {
            $agent = $bk->bookedBy;
            if (!$agent) continue;

            $masterLead = $agent->masterLead ?? $agent->brokerCompany?->masterLead;
            if (!$masterLead && \App\Models\Setting::get('commission_schema_config.enable_master_lead', true)) {
                $masterLead = User::where('agent_type', 'master_lead')
                    ->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))
                    ->first();
            }

            if ($masterLead && $masterLead->id !== $agent->id) {
                $masterRate = (float)\App\Models\Setting::get('default_commission_rates.master_lead_overriding', ($masterLead->commission_rate > 0 ? (float)$masterLead->commission_rate : 4.5));
                $finalPrice = $bk->final_price > 0 ? $bk->final_price : ($bk->unit_price ?? 0);
                $masterTotalGross = $finalPrice * ($masterRate / 100);

                // Cleanup legacy standalone claim rows to ensure 1 row per unit deal
                Commission::where('booking_id', $bk->id)
                    ->where('payout_recipient', 'master_lead')
                    ->whereIn('claim_type', ['closing_fee', 'reward'])
                    ->delete();

                $closingFeeAmount = (float) \App\Models\Setting::get('default_commission_rates.master_lead_closing_fee', 5000000);
                $rewardCashAmount = (float) \App\Models\Setting::get('default_commission_rates.master_lead_reward_iphone_value', 23000000);
                $rewardName = \App\Models\Setting::get('default_commission_rates.master_lead_reward_iphone_name', 'Reward iPhone 17 Pro (Konversi Cash)');

                $existingMlComm = Commission::where('booking_id', $bk->id)
                    ->where('payout_recipient', 'master_lead')
                    ->first();

                if (!$existingMlComm) {
                    Commission::create([
                        'booking_id' => $bk->id,
                        'user_id' => $masterLead->id,
                        'broker_company_id' => null,
                        'amount' => $masterTotalGross,
                        'base_commission' => $masterTotalGross,
                        'closing_fee' => $closingFeeAmount,
                        'reward_value' => $rewardCashAmount,
                        'reward_name' => $rewardName,
                        'promo_bonus' => 0,
                        'rate_used' => $masterRate,
                        'payout_recipient' => 'master_lead',
                        'claim_type' => 'package',
                        'status' => 'pending',
                    ]);
                } else if ($existingMlComm->status === 'pending') {
                    $existingMlComm->update([
                        'rate_used' => $masterRate,
                        'base_commission' => $masterTotalGross,
                        'amount' => $masterTotalGross,
                        'closing_fee' => $closingFeeAmount,
                        'reward_value' => $rewardCashAmount,
                        'reward_name' => $rewardName,
                        'claim_type' => 'package',
                    ]);
                }
            }
        }

        // 1. Query Master Lead Partners with Sub-Agents
        $mlQuery = User::withCount(['subAgents'])
            ->with(['subAgents' => function ($q) {
                $q->withCount(['commissions'])
                  ->withSum('commissions', 'amount')
                  ->with('brokerCompany');
            }])
            ->where(function ($q) {
                $q->where('agent_type', 'master_lead')
                  ->orWhereHas('roles', fn($rq) => $rq->where('name', 'master_lead'));
            });

        if ($isMasterLead) {
            $mlQuery->where('id', $user->id);
        }

        $masterLeads = $mlQuery
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15, ['*'], 'ml_page')
            ->through(function ($ml) {
                $teamUserIds = User::where('master_lead_id', $ml->id)->pluck('id')->push($ml->id);

                $totalBookings = Booking::whereIn('booked_by', $teamUserIds)
                    ->whereIn('status', ['approved', 'completed', 'booked'])
                    ->count();

                $totalRevenue = Booking::whereIn('booked_by', $teamUserIds)
                    ->whereIn('status', ['approved', 'completed', 'booked'])
                    ->sum('final_price');

                $overridingCommissions = Commission::where('user_id', $ml->id)
                    ->where('payout_recipient', 'master_lead')
                    ->sum('amount');

                return [
                    'id' => $ml->id,
                    'name' => $ml->name,
                    'email' => $ml->email,
                    'phone' => $ml->phone,
                    'commission_rate' => $ml->commission_rate ?: \App\Models\Setting::get('default_commission_rates.master_lead_overriding', 4.5),
                    'status' => $ml->status,
                    'bank_name' => $ml->bank_name,
                    'bank_account_number' => $ml->bank_account_number,
                    'bank_account_name' => $ml->bank_account_name,
                    'sub_agents_count' => $ml->sub_agents_count,
                    'total_bookings' => $totalBookings,
                    'total_revenue' => (float)$totalRevenue,
                    'overriding_commissions' => (float)$overridingCommissions,
                    'sub_agents' => $ml->subAgents->map(function ($sa) {
                        $saBookingsCount = Booking::where('booked_by', $sa->id)
                            ->whereIn('status', ['approved', 'completed', 'booked'])
                            ->count();
                        $saRevenue = Booking::where('booked_by', $sa->id)
                            ->whereIn('status', ['approved', 'completed', 'booked'])
                            ->sum('final_price');

                        return [
                            'id' => $sa->id,
                            'name' => $sa->name,
                            'email' => $sa->email,
                            'phone' => $sa->phone,
                            'agent_type' => $sa->agent_type,
                            'commission_rate' => $sa->commission_rate,
                            'broker_company_id' => $sa->broker_company_id,
                            'broker_company_name' => $sa->brokerCompany?->name,
                            'broker_company_code' => $sa->brokerCompany?->code,
                            'bank_name' => $sa->bank_name,
                            'bank_account_number' => $sa->bank_account_number,
                            'bank_account_name' => $sa->bank_account_name,
                            'effective_bank_account' => $sa->effective_bank_account,
                            'total_bookings' => $saBookingsCount,
                            'total_revenue' => (float)$saRevenue,
                            'total_commissions' => (float)($sa->commissions_sum_amount ?? 0),
                        ];
                    }),
                    'created_at' => $ml->created_at->format('d M Y'),
                ];
            });

        // 2. Query Broker Companies (Kantor Agency)
        $brokerQuery = BrokerCompany::withCount(['agents', 'commissions'])
            ->withSum('commissions', 'amount')
            ->withSum(['commissions as paid_commissions_sum' => fn($q) => $q->where('status', 'paid')], 'amount')
            ->withSum(['commissions as pending_commissions_sum' => fn($q) => $q->where('status', 'unpaid')], 'amount');

        if ($isMasterLead) {
            $brokerQuery->where('master_lead_id', $user->id);
        }

        $brokers = $brokerQuery
            ->when($request->search_agency, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->latest()
            ->paginate(15, ['*'], 'brokers_page');

        // 3. Query All Flat Agents
        $agentsQuery = User::with(['brokerCompany', 'roles', 'masterLead'])
            ->where(function ($q) {
                $q->whereNotNull('agent_type')
                  ->orWhereHas('roles', fn($rq) => $rq->whereIn('name', ['sales_agent', 'broker', 'sales_manager', 'agent', 'master_lead']));
            });

        if ($isMasterLead) {
            $agentsQuery->where('master_lead_id', $user->id);
        }

        $allAgents = $agentsQuery
            ->when($request->search_agent, fn($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))
            ->latest()
            ->paginate(20, ['*'], 'agents_page');

        // 4. Ledger & Sub-Agent Payout Query
        $ledgerQuery = Commission::with(['user.masterLead', 'user.brokerCompany', 'booking.lead', 'booking.unit.project'])
            ->whereHas('booking')
            ->whereHas('user', function($q) use ($isMasterLead, $user) {
                $q->whereNotNull('master_lead_id');
                if ($isMasterLead) {
                    $q->where('master_lead_id', $user->id);
                }
            });

        $subAgentCommissions = (clone $ledgerQuery)
            ->when($request->search_ledger, function ($q, $s) {
                $q->whereHas('user', fn($uq) => $uq->where('name', 'like', "%{$s}%"))
                  ->orWhereHas('booking.lead', fn($lq) => $lq->where('name', 'like', "%{$s}%"))
                  ->orWhere('ml_receipt_number', 'like', "%{$s}%");
            })
            ->latest()
            ->paginate(15, ['*'], 'ledger_page');

        // Cashflow stats for Master Lead
        $allMlOverridingCommissions = Commission::where('payout_recipient', 'master_lead')
            ->when($isMasterLead, fn($q) => $q->where('user_id', $user->id))
            ->sum('amount');

        $subAgentTotalPotency = (clone $ledgerQuery)->sum('amount');
        $subAgentPaidOutflow = (clone $ledgerQuery)->where('ml_payout_status', 'paid')->sum('amount');
        $subAgentPendingOutflow = (clone $ledgerQuery)->where(fn($q) => $q->where('ml_payout_status', 'unpaid')->orWhereNull('ml_payout_status'))->sum('amount');

        // Stats summary
        $totalMasterLeads = User::where('agent_type', 'master_lead')->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))->count();
        $totalSubAgents = User::whereNotNull('master_lead_id')->count();
        $totalBrokers = BrokerCompany::count();
        $totalMasterLeadRevenue = Booking::whereHas('bookedBy', fn($q) => $q->whereNotNull('master_lead_id')->orWhere('agent_type', 'master_lead'))
            ->whereIn('status', ['approved', 'completed', 'booked'])
            ->sum('final_price');

        $withRelations = ['user', 'booking.lead', 'booking.unit.project'];
        if (\Illuminate\Support\Facades\Schema::hasColumn('commissions', 'master_lead_invoice_id')) {
            $withRelations[] = 'masterLeadInvoice';
        }

        $mlOverridingCommissions = Commission::with($withRelations)
            ->where('payout_recipient', 'master_lead')
            ->whereHas('booking')
            ->when($isMasterLead, fn($q) => $q->where('user_id', $user->id))
            ->latest()
            ->get();

        $masterLeadInvoices = \Illuminate\Support\Facades\Schema::hasTable('master_lead_invoices')
            ? MasterLeadInvoice::with(['masterLead', 'commissions.booking.unit.project'])
                ->when($isMasterLead, fn($q) => $q->where('master_lead_id', $user->id))
                ->latest()
                ->get()
            : collect([]);

        return Inertia::render('MasterLeads/Index', [
            'masterLeads' => $masterLeads,
            'brokers' => $brokers,
            'allAgents' => $allAgents,
            'subAgentCommissions' => $subAgentCommissions,
            'mlOverridingCommissions' => $mlOverridingCommissions,
            'masterLeadInvoices' => $masterLeadInvoices,
            'brokerList' => BrokerCompany::where('status', 'active')->select('id', 'name', 'code', 'commission_rate')->get(),
            'masterLeadList' => User::where('agent_type', 'master_lead')->orWhereHas('roles', fn($q) => $q->where('name', 'master_lead'))->select('id', 'name', 'phone')->get(),
            'stats' => [
                'total_master_leads' => $totalMasterLeads,
                'total_sub_agents' => $totalSubAgents,
                'total_brokers' => $totalBrokers,
                'total_revenue' => (float)$totalMasterLeadRevenue,
                'net_overriding_ml' => (float)$allMlOverridingCommissions,
                'sub_agent_total_potency' => (float)$subAgentTotalPotency,
                'sub_agent_paid_outflow' => (float)$subAgentPaidOutflow,
                'sub_agent_pending_outflow' => (float)$subAgentPendingOutflow,
                'default_overriding_rate' => \App\Models\Setting::get('default_commission_rates.master_lead_overriding', 4.5),
            ],
            'filters' => $request->only(['search', 'search_agency', 'search_agent', 'search_ledger']),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:6',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:255',
            ]);

            $plainPassword = $request->password;

            $companyId = auth()->user()?->company_id;
            if (!$companyId && \App\Models\Company::exists()) {
                $companyId = \App\Models\Company::first()->id;
            }

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'company_id' => $companyId,
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($plainPassword),
                'agent_type' => 'master_lead',
                'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : 4.5,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
                'status' => 'active',
            ]);

            try {
                \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'master_lead', 'guard_name' => 'web']);
                $user->assignRole('master_lead');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Spatie role assign fallback: ' . $e->getMessage());
            }

            try {
                AuditLog::record('master_lead_created', $user, null, $user->toArray());
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('AuditLog record fallback: ' . $e->getMessage());
            }

            $loginUrl = url('/login');

            return back()->with('success', 'Akun Master Lead baru berhasil dibuat.')
                ->with('new_credentials', [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'password' => $plainPassword,
                    'login_url' => $loginUrl,
                ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController store failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal membuat Master Lead: ' . $e->getMessage());
        }
    }

    public function update(Request $request, User $user)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'password' => 'nullable|string|min:6',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'status' => 'required|in:active,inactive',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:255',
            ]);

            $data = [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'status' => $validated['status'],
                'commission_rate' => (is_numeric($request->commission_rate) && $request->commission_rate > 0) ? (float)$request->commission_rate : $user->commission_rate,
                'bank_name' => $validated['bank_name'] ?? null,
                'bank_account_number' => $validated['bank_account_number'] ?? null,
                'bank_account_name' => $validated['bank_account_name'] ?? null,
            ];

            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $old = $user->toArray();
            $user->update($data);

            try {
                AuditLog::record('master_lead_updated', $user, $old, $user->toArray());
            } catch (\Throwable $e) {}

            return back()->with('success', 'Data Master Lead berhasil diperbarui.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController update failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui Master Lead: ' . $e->getMessage());
        }
    }

    public function destroy(User $user)
    {
        try {
            if ($user->id === auth()->id()) {
                return back()->with('error', 'Tidak bisa menghapus akun Anda sendiri.');
            }

            try {
                AuditLog::record('master_lead_deleted', $user, $user->toArray(), null);
            } catch (\Throwable $e) {}

            // Disassociate sub agents
            User::where('master_lead_id', $user->id)->update(['master_lead_id' => null]);
            $user->delete();

            return back()->with('success', 'Akun Master Lead berhasil dihapus.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController destroy failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal menghapus Master Lead: ' . $e->getMessage());
        }
    }

    public function paySubAgentCommission(Request $request, Commission $commission)
    {
        try {
            $validated = $request->validate([
                'receipt_number' => 'nullable|string|max:100',
                'notes' => 'nullable|string|max:500',
            ]);

            $receipt = $validated['receipt_number'] ?: ('TRF-ML-' . strtoupper(uniqid()));

            $commission->update([
                'ml_payout_status' => 'paid',
                'ml_paid_at' => now(),
                'ml_receipt_number' => $receipt,
                'notes' => $validated['notes'] ?? $commission->notes,
            ]);

            try {
                AuditLog::record('master_lead_sub_agent_paid', $commission, null, $commission->toArray());
            } catch (\Throwable $e) {}

            return back()->with('success', "Penyaluran komisi ke Sub-Agent berhasil dicatat. No. Ref: {$receipt}");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController paySubAgentCommission failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal mencatat transfer ke Sub-Agent: ' . $e->getMessage());
        }
    }

    public function storeInvoice(Request $request)
    {
        try {
            $validated = $request->validate([
                'commission_ids' => 'required|array|min:1',
                'commission_ids.*' => 'exists:commissions,id',
                'invoice_type' => 'nullable|in:commission,closing_fee,reward',
                'reward_name' => 'nullable|string|max:255',
                'fee_per_unit' => 'nullable|numeric|min:0',
                'custom_amount' => 'nullable|numeric|min:0',
                'notes' => 'nullable|string|max:500',
            ]);

            $commissions = Commission::whereIn('id', $validated['commission_ids'])
                ->where('payout_recipient', 'master_lead')
                ->get();

            if ($commissions->isEmpty()) {
                return back()->with('error', 'Tidak ada komisi Master Lead yang valid untuk dibuatkan invoice.');
            }

            $invoiceType = $validated['invoice_type'] ?? 'commission';
            $firstComm = $commissions->first();
            $masterLead = User::find($firstComm->user_id) ?? auth()->user();
            $invoiceNumber = MasterLeadInvoice::generateInvoiceNumber($masterLead, $invoiceType);

            $defaultClosingFeePerUnit = (float) \App\Models\Setting::get('default_commission_rates.master_lead_closing_fee', 2500000);
            $defaultIphoneRewardCash = (float) \App\Models\Setting::get('default_commission_rates.master_lead_reward_iphone_value', 20000000);

            if ($invoiceType === 'closing_fee') {
                $feePerUnit = isset($validated['fee_per_unit']) && $validated['fee_per_unit'] > 0
                    ? (float) $validated['fee_per_unit']
                    : $defaultClosingFeePerUnit;
                $totalAmount = $feePerUnit * $commissions->count();
                $rewardName = null;
            } else if ($invoiceType === 'package') {
                $feePerUnit = null;
                $rewardName = 'Paket Lengkap (Komisi + Closing Fee + Reward)';
                $totalAmount = $commissions->reduce(function ($sum, $c) {
                    return $sum + (float)$c->amount + (float)$c->closing_fee + (float)$c->reward_value;
                }, 0);
            } else if ($invoiceType === 'reward') {
                $feePerUnit = null;
                $rewardName = $validated['reward_name'] ?: 'Reward iPhone 16 Pro 256GB (Konversi Cash)';
                $totalAmount = (isset($validated['custom_amount']) && $validated['custom_amount'] > 0)
                    ? (float) $validated['custom_amount']
                    : ($commissions->sum('reward_value') ?: $defaultIphoneRewardCash);
            } else {
                $feePerUnit = null;
                $rewardName = null;
                $totalAmount = $commissions->sum('amount');
            }

            $invoice = MasterLeadInvoice::create([
                'invoice_number' => $invoiceNumber,
                'master_lead_id' => $masterLead->id,
                'invoice_type' => $invoiceType,
                'reward_name' => $rewardName,
                'fee_per_unit' => $feePerUnit,
                'total_amount' => $totalAmount,
                'status' => 'submitted',
                'notes' => $validated['notes'] ?? null,
                'submitted_at' => now(),
            ]);

            foreach ($commissions as $comm) {
                $comm->update(['master_lead_invoice_id' => $invoice->id]);
            }

            try {
                AuditLog::record('master_lead_invoice_created', $invoice, null, $invoice->toArray());
            } catch (\Throwable $e) {}

            $typeLabel = match($invoiceType) {
                'closing_fee' => 'Closing Fee',
                'reward' => 'Reward iPhone',
                default => 'Komisi Overriding',
            };

            return redirect()->route('master-leads.invoices.show', $invoice->id)
                ->with('success', "Invoice Tagihan {$typeLabel} {$invoiceNumber} berhasil diterbitkan.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController storeInvoice failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal menerbitkan Invoice: ' . $e->getMessage());
        }
    }

    public function showInvoice(MasterLeadInvoice $invoice)
    {
        $invoice->load([
            'masterLead',
            'commissions.booking.lead',
            'commissions.booking.unit.project',
            'commissions.booking.bookedBy',
            'commissions.booking.bankAccount',
        ]);

        $spelledText = ucwords(trim($this->terbilang($invoice->total_amount))) . " Rupiah";
        $settingsRaw = \App\Models\Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = \App\Models\Setting::get($s->key);
        }

        return Inertia::render('MasterLeads/InvoiceReceipt', [
            'invoice' => $invoice,
            'spelled_text' => $spelledText,
            'developerName' => $developerName,
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function updateParameters(Request $request)
    {
        $validated = $request->validate([
            'master_lead_overriding' => 'required|numeric|min:0|max:100',
            'master_lead_closing_fee' => 'required|numeric|min:0',
            'master_lead_reward_iphone_value' => 'required|numeric|min:0',
            'master_lead_reward_iphone_name' => 'required|string',
        ]);

        $defaultRates = \App\Models\Setting::get('default_commission_rates', []);
        $defaultRates['master_lead_overriding'] = (float) $validated['master_lead_overriding'];
        $defaultRates['master_lead_closing_fee'] = (float) $validated['master_lead_closing_fee'];
        $defaultRates['master_lead_reward_iphone_value'] = (float) $validated['master_lead_reward_iphone_value'];
        $defaultRates['master_lead_reward_iphone_name'] = $validated['master_lead_reward_iphone_name'];

        \App\Models\Setting::set('default_commission_rates', $defaultRates);

        Commission::where('payout_recipient', 'master_lead')
            ->where('status', 'pending')
            ->get()
            ->each(function ($comm) use ($defaultRates) {
                $finalPrice = $comm->booking?->final_price > 0 ? $comm->booking->final_price : ($comm->booking?->unit_price ?? 0);
                $comm->update([
                    'rate_used' => $defaultRates['master_lead_overriding'],
                    'amount' => $finalPrice * ($defaultRates['master_lead_overriding'] / 100),
                    'base_commission' => $finalPrice * ($defaultRates['master_lead_overriding'] / 100),
                    'closing_fee' => $defaultRates['master_lead_closing_fee'],
                    'reward_value' => $defaultRates['master_lead_reward_iphone_value'],
                    'reward_name' => $defaultRates['master_lead_reward_iphone_name'],
                ]);
            });

        return back()->with('success', 'Parameter Closing Fee, Reward iPhone, dan Komisi Overriding berhasil diperbarui!');
    }

    public function updateInvoiceDetail(Request $request, MasterLeadInvoice $invoice)
    {
        $validated = $request->validate([
            'total_amount' => 'required|numeric|min:0',
            'reward_name' => 'nullable|string',
            'fee_per_unit' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'bank_account_1_name' => 'nullable|string',
            'bank_account_1_number' => 'nullable|string',
            'bank_account_1_holder' => 'nullable|string',
            'bank_account_2_name' => 'nullable|string',
            'bank_account_2_number' => 'nullable|string',
            'bank_account_2_holder' => 'nullable|string',
        ]);

        $invoice->update([
            'total_amount' => (float) $validated['total_amount'],
            'reward_name' => $validated['reward_name'] ?? $invoice->reward_name,
            'fee_per_unit' => isset($validated['fee_per_unit']) ? (float)$validated['fee_per_unit'] : $invoice->fee_per_unit,
            'notes' => $validated['notes'] ?? $invoice->notes,
            'bank_account_1_name' => $validated['bank_account_1_name'] ?? $invoice->bank_account_1_name,
            'bank_account_1_number' => $validated['bank_account_1_number'] ?? $invoice->bank_account_1_number,
            'bank_account_1_holder' => $validated['bank_account_1_holder'] ?? $invoice->bank_account_1_holder,
            'bank_account_2_name' => $validated['bank_account_2_name'] ?? $invoice->bank_account_2_name,
            'bank_account_2_number' => $validated['bank_account_2_number'] ?? $invoice->bank_account_2_number,
            'bank_account_2_holder' => $validated['bank_account_2_holder'] ?? $invoice->bank_account_2_holder,
        ]);

        return back()->with('success', 'Detail Invoice & Rekening berhasil diperbarui.');
    }

    public function markInvoicePaid(Request $request, MasterLeadInvoice $invoice)
    {
        try {
            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
            ]);

            foreach ($invoice->commissions as $comm) {
                $comm->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                ]);
            }

            try {
                AuditLog::record('master_lead_invoice_paid', $invoice, null, $invoice->toArray());
            } catch (\Throwable $e) {}

            return back()->with('success', "Invoice {$invoice->invoice_number} berhasil ditandai Lunas / Cair dari Developer.");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController markInvoicePaid failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal memproses status Lunas: ' . $e->getMessage());
        }
    }

    public function updateInvoiceBank(Request $request, MasterLeadInvoice $invoice)
    {
        try {
            $validated = $request->validate([
                'total_amount' => 'nullable|numeric|min:0',
                'reward_name' => 'nullable|string',
                'fee_per_unit' => 'nullable|numeric',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:100',
                'bank_account_name' => 'nullable|string|max:255',
                'secondary_bank_name' => 'nullable|string|max:100',
                'secondary_bank_account_number' => 'nullable|string|max:100',
                'secondary_bank_account_name' => 'nullable|string|max:255',
            ]);

            if ($request->has('total_amount') && (float)$request->total_amount > 0) {
                $invoice->update([
                    'total_amount' => (float)$request->total_amount,
                    'reward_name' => $request->reward_name ?: $invoice->reward_name,
                    'fee_per_unit' => $request->fee_per_unit ?: $invoice->fee_per_unit,
                ]);
            }

            $masterLead = $invoice->masterLead;
            if ($masterLead) {
                $settings = $masterLead->settings ?: [];
                if ($request->has('secondary_bank_name') || $request->has('secondary_bank_account_number')) {
                    $settings['secondary_bank_name'] = $request->secondary_bank_name;
                    $settings['secondary_bank_account_number'] = $request->secondary_bank_account_number;
                    $settings['secondary_bank_account_name'] = $request->secondary_bank_account_name;
                }

                $masterLead->update([
                    'bank_name' => $request->bank_name ?: $masterLead->bank_name,
                    'bank_account_number' => $request->bank_account_number ?: $masterLead->bank_account_number,
                    'bank_account_name' => $request->bank_account_name ?: $masterLead->bank_account_name,
                    'settings' => $settings,
                ]);
            }

            return back()->with('success', 'Detail Nominal Invoice & Rekening Bank berhasil diperbarui.');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('MasterLeadController updateInvoiceBank failed: ' . $e->getMessage());
            return back()->with('error', 'Gagal memperbarui rekening bank: ' . $e->getMessage());
        }
    }

    private function terbilang($angka)
    {
        $angka = abs($angka);
        $baca = array("", "Satu", "Dua", "Tiga", "Empat", "Lima", "Enam", "Tujuh", "Delapan", "Sembilan", "Sepuluh", "Sebelas");
        $terbilang = "";

        if ($angka < 12) {
            $terbilang = " " . $baca[$angka];
        } else if ($angka < 20) {
            $terbilang = $this->terbilang($angka - 10) . " Belas";
        } else if ($angka < 100) {
            $terbilang = $this->terbilang($angka / 10) . " Puluh" . $this->terbilang($angka % 10);
        } else if ($angka < 200) {
            $terbilang = " Seratus" . $this->terbilang($angka - 100);
        } else if ($angka < 1000) {
            $terbilang = $this->terbilang($angka / 100) . " Ratus" . $this->terbilang($angka % 100);
        } else if ($angka < 2000) {
            $terbilang = " Seribu" . $this->terbilang($angka - 1000);
        } else if ($angka < 1000000) {
            $terbilang = $this->terbilang($angka / 1000) . " Ribu" . $this->terbilang($angka % 1000);
        } else if ($angka < 1000000000) {
            $terbilang = $this->terbilang($angka / 1000000) . " Juta" . $this->terbilang($angka % 1000000);
        } else if ($angka < 1000000000000) {
            $terbilang = $this->terbilang($angka / 1000000000) . " Milyar" . $this->terbilang(fmod($angka, 1000000000));
        }

        return $terbilang;
    }
}
