<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientBalanceSheet extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'booking_id', 'created_by', 'client_name', 'company_name',
        'business_type', 'phone',
        'cash_and_bank', 'inventory', 'receivables', 'other_current_assets',
        'equipment', 'vehicles', 'machinery_and_buildings', 'accumulated_depreciation',
        'trade_payables', 'bank_loans', 'other_liabilities',
        'capital', 'retained_earnings', 'drawings_prive',
        'monthly_revenue', 'monthly_net_profit', 'existing_monthly_debt_service',
        'target_kpr_amount', 'target_tenor_years', 'target_interest_rate',
        'current_ratio', 'der_ratio', 'dsr_ratio', 'approval_score',
        'analysis_summary', 'notes'
    ];

    protected $casts = [
        'analysis_summary' => 'array',
        'cash_and_bank' => 'float',
        'inventory' => 'float',
        'receivables' => 'float',
        'other_current_assets' => 'float',
        'equipment' => 'float',
        'vehicles' => 'float',
        'machinery_and_buildings' => 'float',
        'accumulated_depreciation' => 'float',
        'trade_payables' => 'float',
        'bank_loans' => 'float',
        'other_liabilities' => 'float',
        'capital' => 'float',
        'retained_earnings' => 'float',
        'drawings_prive' => 'float',
        'monthly_revenue' => 'float',
        'monthly_net_profit' => 'float',
        'existing_monthly_debt_service' => 'float',
        'target_kpr_amount' => 'float',
        'target_interest_rate' => 'float',
        'current_ratio' => 'float',
        'der_ratio' => 'float',
        'dsr_ratio' => 'float',
    ];

    // Relationships
    public function lead() { return $this->belongsTo(Lead::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }

    // Computed Accessors
    public function getTotalAktivaLancarAttribute(): float
    {
        return $this->cash_and_bank + $this->inventory + $this->receivables + $this->other_current_assets;
    }

    public function getTotalAktivaTetapAttribute(): float
    {
        return ($this->equipment + $this->vehicles + $this->machinery_and_buildings) - $this->accumulated_depreciation;
    }

    public function getTotalAktivaAttribute(): float
    {
        return $this->total_aktiva_lancar + $this->total_aktiva_tetap;
    }

    public function getTotalKewajibanAttribute(): float
    {
        return $this->trade_payables + $this->bank_loans + $this->other_liabilities;
    }

    public function getTotalEkuitasAttribute(): float
    {
        return ($this->capital + $this->retained_earnings) - $this->drawings_prive;
    }

    public function getTotalPasivaAttribute(): float
    {
        return $this->total_kewajiban + $this->total_ekuitas;
    }

    public function getIsBalancedAttribute(): bool
    {
        return abs($this->total_aktiva - $this->total_pasiva) < 100;
    }

    public function getEstimatedNewKprInstallmentAttribute(): float
    {
        if ($this->target_kpr_amount <= 0 || $this->target_tenor_years <= 0) return 0;
        
        $months = $this->target_tenor_years * 12;
        $monthlyRate = ($this->target_interest_rate / 100) / 12;
        
        if ($monthlyRate <= 0) {
            return $this->target_kpr_amount / $months;
        }

        return ($this->target_kpr_amount * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$months));
    }

    // Perform Auto Calculation & Scoring
    public function calculateRatiosAndScore(): void
    {
        // 1. Current Ratio = Aktiva Lancar / Kewajiban
        $liabilities = $this->total_kewajiban;
        $this->current_ratio = $liabilities > 0 ? round($this->total_aktiva_lancar / $liabilities, 2) : 9.99;

        // 2. DER = Total Kewajiban / Total Ekuitas
        $equity = $this->total_ekuitas;
        $this->der_ratio = $equity > 0 ? round($liabilities / $equity, 2) : 9.99;

        // 3. DSR = (Existing Debt + New KPR Installment) / Monthly Net Profit
        $newInstallment = $this->estimated_new_kpr_installment;
        $totalMonthlyDebt = $this->existing_monthly_debt_service + $newInstallment;
        $netProfit = $this->monthly_net_profit;

        $this->dsr_ratio = $netProfit > 0 ? round(($totalMonthlyDebt / $netProfit) * 100, 2) : 100.0;

        // 4. Score Determination
        // High: CR >= 1.5, DER <= 1.5, DSR <= 35%
        // Medium: CR >= 1.0, DER <= 2.5, DSR <= 50%
        // Low: CR < 1.0 OR DER > 2.5 OR DSR > 50%
        if ($this->current_ratio >= 1.5 && $this->der_ratio <= 1.5 && $this->dsr_ratio <= 35.0) {
            $this->approval_score = 'high';
        } elseif ($this->current_ratio >= 1.0 && $this->der_ratio <= 2.5 && $this->dsr_ratio <= 50.0) {
            $this->approval_score = 'medium';
        } else {
            $this->approval_score = 'low';
        }

        $this->analysis_summary = [
            'total_aktiva' => $this->total_aktiva,
            'total_pasiva' => $this->total_pasiva,
            'is_balanced' => $this->is_balanced,
            'estimated_installment' => round($newInstallment, 0),
            'current_ratio_label' => $this->current_ratio >= 1.5 ? 'Sehat (Likuid)' : 'Waspada (Likuiditas Rendah)',
            'der_label' => $this->der_ratio <= 1.5 ? 'Aman (Solvabel)' : 'Tinggi (Beban Hutang)',
            'dsr_label' => $this->dsr_ratio <= 35 ? 'Sangat Layak (DSR < 35%)' : ($this->dsr_ratio <= 50 ? 'Cukup Layak (DSR < 50%)' : 'Rentan Tolak (DSR > 50%)'),
        ];
    }
}
