<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingKprTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->project = Project::create([
            'name' => 'Homi Cluster A',
            'code' => 'P-HCM',
            'location' => 'Bandung',
            'description' => 'Cluster premium',
            'status' => 'active',
        ]);
        $this->unitType = UnitType::create([
            'project_id' => $this->project->id,
            'name' => 'Tipe 36',
            'base_price' => 500000000,
        ]);
        $this->unit = Unit::create([
            'project_id' => $this->project->id,
            'unit_type_id' => $this->unitType->id,
            'block' => 'A',
            'number' => '10',
            'status' => 'available',
        ]);
        $this->lead = Lead::create([
            'project_id' => $this->project->id,
            'name' => 'John Doe',
            'phone' => '081234567890',
            'status' => 'new',
        ]);

        $this->booking = Booking::create([
            'spk_number' => 'SPK-1001',
            'unit_id' => $this->unit->id,
            'lead_id' => $this->lead->id,
            'project_id' => $this->project->id,
            'booked_by' => $this->user->id,
            'booking_fee' => 10000000,
            'unit_price' => 500000000,
            'base_price' => 500000000,
            'final_price' => 500000000,
            'payment_scheme' => 'kpr',
            'booking_date' => now(),
            'status' => 'approved',
            'kpr_status' => 'pending',
        ]);
    }

    public function test_user_can_update_kpr_progress_stages()
    {
        // Test update to appraisal
        $response = $this->actingAs($this->user)
            ->post(route('bookings.updateKpr', $this->booking->id), [
                'kpr_status' => 'appraisal',
                'kpr_bank_name' => 'BCA',
                'kpr_plafon_amount' => 450000000,
                'kpr_notes' => 'Berkas disetujui analis BCA',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'kpr_status' => 'appraisal',
            'kpr_bank_name' => 'BCA',
            'kpr_plafon_amount' => 450000000,
        ]);

        // Test update to sp3k (the one that previously failed check constraint)
        $response = $this->actingAs($this->user)
            ->post(route('bookings.updateKpr', $this->booking->id), [
                'kpr_status' => 'sp3k',
                'kpr_bank_name' => 'BCA',
                'kpr_plafon_amount' => 450000000,
                'kpr_notes' => 'SP3K Terbit',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('bookings', [
            'id' => $this->booking->id,
            'kpr_status' => 'sp3k',
        ]);
    }
}
