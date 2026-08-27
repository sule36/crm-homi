<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ClientBalanceSheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBalanceSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_kpr_scoring_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/kpr-scoring');

        $response->assertStatus(200);
    }

    public function test_can_create_client_balance_sheet_and_auto_calculate_ratios(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/client-balance-sheets', [
            'client_name' => 'Budi Pengusaha',
            'company_name' => 'CV Maju Jaya',
            'business_type' => 'Perdagangan',
            'cash_and_bank' => 200000000,
            'inventory' => 300000000,
            'receivables' => 100000000,
            'equipment' => 100000000,
            'vehicles' => 200000000,
            'machinery_and_buildings' => 500000000,
            'accumulated_depreciation' => 50000000,

            // Pasiva
            'trade_payables' => 100000000,
            'bank_loans' => 200000000,
            'capital' => 500000000,
            'retained_earnings' => 550000000,
            'drawings_prive' => 0,

            // Monthly profit
            'monthly_revenue' => 150000000,
            'monthly_net_profit' => 45000000,
            'existing_monthly_debt_service' => 5000000,

            // Target KPR
            'target_kpr_amount' => 1200000000,
            'target_tenor_years' => 15,
            'target_interest_rate' => 5.0,
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('client_balance_sheets', [
            'client_name' => 'Budi Pengusaha',
            'approval_score' => 'high',
        ]);
    }
}
