<?php

namespace App\Http\Controllers;

use App\Models\ClientBalanceSheet;
use App\Models\Lead;
use App\Models\Booking;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ClientBalanceSheetController extends Controller
{
    public function index(Request $request)
    {
        $sheets = ClientBalanceSheet::with(['lead', 'booking.unit.project', 'creator'])
            ->when($request->search, function ($q, $s) {
                $q->where('client_name', 'like', "%{$s}%")
                  ->orWhere('company_name', 'like', "%{$s}%")
                  ->orWhere('phone', 'like', "%{$s}%");
            })
            ->when($request->approval_score, fn($q, $score) => $q->where('approval_score', $score))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = [
            'total_analyzed' => ClientBalanceSheet::count(),
            'high_approval' => ClientBalanceSheet::where('approval_score', 'high')->count(),
            'medium_approval' => ClientBalanceSheet::where('approval_score', 'medium')->count(),
            'low_approval' => ClientBalanceSheet::where('approval_score', 'low')->count(),
        ];

        return Inertia::render('ClientBalanceSheet/Index', [
            'sheets' => $sheets,
            'stats' => $stats,
            'leads' => Lead::select('id', 'name', 'phone')->get(),
            'bookings' => Booking::with('lead', 'unit')->get(),
            'filters' => $request->only(['search', 'approval_score']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'lead_id' => 'nullable|exists:leads,id',
            'booking_id' => 'nullable|exists:bookings,id',
            
            // Aktiva
            'cash_and_bank' => 'required|numeric|min:0',
            'inventory' => 'required|numeric|min:0',
            'receivables' => 'required|numeric|min:0',
            'other_current_assets' => 'nullable|numeric|min:0',
            'equipment' => 'required|numeric|min:0',
            'vehicles' => 'required|numeric|min:0',
            'machinery_and_buildings' => 'required|numeric|min:0',
            'accumulated_depreciation' => 'nullable|numeric|min:0',

            // Pasiva
            'trade_payables' => 'required|numeric|min:0',
            'bank_loans' => 'required|numeric|min:0',
            'other_liabilities' => 'nullable|numeric|min:0',
            'capital' => 'required|numeric|min:0',
            'retained_earnings' => 'required|numeric',
            'drawings_prive' => 'nullable|numeric|min:0',

            // Profit & KPR
            'monthly_revenue' => 'required|numeric|min:0',
            'monthly_net_profit' => 'required|numeric|min:0',
            'existing_monthly_debt_service' => 'nullable|numeric|min:0',
            'target_kpr_amount' => 'required|numeric|min:0',
            'target_tenor_years' => 'required|integer|min:1|max:30',
            'target_interest_rate' => 'required|numeric|min:0|max:30',
            'notes' => 'nullable|string',
        ]);

        $validated['created_by'] = auth()->id();
        
        $sheet = new ClientBalanceSheet($validated);
        $sheet->calculateRatiosAndScore();
        $sheet->save();

        AuditLog::record('client_balance_sheet_created', $sheet, null, $sheet->toArray());

        return back()->with('success', 'Analisis Neraca Client berhasil disimpan dan diskor.');
    }

    public function update(Request $request, ClientBalanceSheet $clientBalanceSheet)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'lead_id' => 'nullable|exists:leads,id',
            'booking_id' => 'nullable|exists:bookings,id',
            
            // Aktiva
            'cash_and_bank' => 'required|numeric|min:0',
            'inventory' => 'required|numeric|min:0',
            'receivables' => 'required|numeric|min:0',
            'other_current_assets' => 'nullable|numeric|min:0',
            'equipment' => 'required|numeric|min:0',
            'vehicles' => 'required|numeric|min:0',
            'machinery_and_buildings' => 'required|numeric|min:0',
            'accumulated_depreciation' => 'nullable|numeric|min:0',

            // Pasiva
            'trade_payables' => 'required|numeric|min:0',
            'bank_loans' => 'required|numeric|min:0',
            'other_liabilities' => 'nullable|numeric|min:0',
            'capital' => 'required|numeric|min:0',
            'retained_earnings' => 'required|numeric',
            'drawings_prive' => 'nullable|numeric|min:0',

            // Profit & KPR
            'monthly_revenue' => 'required|numeric|min:0',
            'monthly_net_profit' => 'required|numeric|min:0',
            'existing_monthly_debt_service' => 'nullable|numeric|min:0',
            'target_kpr_amount' => 'required|numeric|min:0',
            'target_tenor_years' => 'required|integer|min:1|max:30',
            'target_interest_rate' => 'required|numeric|min:0|max:30',
            'notes' => 'nullable|string',
        ]);

        $oldValues = $clientBalanceSheet->toArray();
        $clientBalanceSheet->fill($validated);
        $clientBalanceSheet->calculateRatiosAndScore();
        $clientBalanceSheet->save();

        AuditLog::record('client_balance_sheet_updated', $clientBalanceSheet, $oldValues, $clientBalanceSheet->toArray());

        return back()->with('success', 'Analisis Neraca Client berhasil diperbarui.');
    }

    public function destroy(ClientBalanceSheet $clientBalanceSheet)
    {
        $oldValues = $clientBalanceSheet->toArray();
        $clientBalanceSheet->delete();
        AuditLog::record('client_balance_sheet_deleted', $clientBalanceSheet, $oldValues, null);
        return back()->with('success', 'Analisis Neraca Client berhasil dihapus.');
    }

    public function show(ClientBalanceSheet $clientBalanceSheet)
    {
        $clientBalanceSheet->load(['lead', 'booking.unit.project', 'creator']);
        return Inertia::render('ClientBalanceSheet/Show', [
            'sheet' => $clientBalanceSheet,
        ]);
    }

    public function create()
    {
        return redirect()->route('client-balance-sheets.index');
    }

    public function edit(ClientBalanceSheet $clientBalanceSheet)
    {
        return redirect()->route('client-balance-sheets.index');
    }
}
