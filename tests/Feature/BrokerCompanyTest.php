<?php

namespace Tests\Feature;

use App\Models\BrokerCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrokerCompanyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_authenticated_user_can_create_broker()
    {
        $response = $this->actingAs($this->user)
            ->post('/settings/brokers', [
                'name' => 'Ray White Central',
                'contact_person' => 'Jane Doe',
                'phone' => '08123456789',
                'email' => 'raywhite@gmail.com',
                'address' => 'Sudirman St. 12',
                'commission_rate' => 3.00,
                'status' => 'active',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('broker_companies', [
            'name' => 'Ray White Central',
            'commission_rate' => 3.00,
            'status' => 'active',
        ]);
    }

    public function test_authenticated_user_can_update_broker()
    {
        $broker = BrokerCompany::create([
            'name' => 'ERA Indonesia',
            'contact_person' => 'John',
            'phone' => '08123123123',
            'email' => 'era@indonesia.com',
            'address' => 'Pakuwon',
            'commission_rate' => 2.50,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->put("/settings/brokers/{$broker->id}", [
                'name' => 'ERA Prima',
                'contact_person' => 'John Doe',
                'phone' => '08123123123',
                'email' => 'era@indonesia.com',
                'address' => 'Pakuwon',
                'commission_rate' => 2.75,
                'status' => 'active',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('broker_companies', [
            'id' => $broker->id,
            'name' => 'ERA Prima',
            'commission_rate' => 2.75,
        ]);
    }

    public function test_authenticated_user_can_delete_broker()
    {
        $broker = BrokerCompany::create([
            'name' => 'Century 21',
            'contact_person' => 'Jack',
            'phone' => '081234567890',
            'email' => 'century21@c21.com',
            'address' => 'Suryalaya',
            'commission_rate' => 3.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/settings/brokers/{$broker->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('broker_companies', [
            'id' => $broker->id,
        ]);
    }
}
