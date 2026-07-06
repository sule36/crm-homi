<?php

namespace Tests\Feature;

use App\Models\PartnerBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerBankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_create_partner_bank()
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/partner-banks', [
                'name' => 'Bank Mandiri Syariah',
                'interest_rate_fixed' => 0.00,
                'interest_rate_floating' => 0.00,
                'fixed_duration' => 0,
                'is_active' => true,
                'is_syariah' => true,
                'syariah_margin_rate' => 6.25,
                'is_tiered' => false,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('partner_banks', [
            'name' => 'Bank Mandiri Syariah',
            'is_syariah' => true,
            'syariah_margin_rate' => 6.25,
        ]);
    }

    public function test_authenticated_user_can_update_partner_bank()
    {
        $bank = PartnerBank::create([
            'name' => 'Bank BCA',
            'interest_rate_fixed' => 4.50,
            'interest_rate_floating' => 11.00,
            'fixed_duration' => 2,
            'is_active' => true,
            'is_syariah' => false,
            'syariah_margin_rate' => 0,
            'is_tiered' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/settings/partner-banks/{$bank->id}", [
                'name' => 'Bank BCA Gold',
                'interest_rate_fixed' => 3.75,
                'interest_rate_floating' => 10.50,
                'fixed_duration' => 3,
                'is_active' => true,
                'is_syariah' => false,
                'syariah_margin_rate' => 0,
                'is_tiered' => false,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('partner_banks', [
            'id' => $bank->id,
            'name' => 'Bank BCA Gold',
            'interest_rate_fixed' => 3.75,
        ]);
    }

    public function test_authenticated_user_can_delete_partner_bank()
    {
        $bank = PartnerBank::create([
            'name' => 'Bank Bukopin',
            'interest_rate_fixed' => 5.50,
            'interest_rate_floating' => 12.00,
            'fixed_duration' => 2,
            'is_active' => true,
            'is_syariah' => false,
            'syariah_margin_rate' => 0,
            'is_tiered' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/settings/partner-banks/{$bank->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('partner_banks', [
            'id' => $bank->id,
        ]);
    }
}
