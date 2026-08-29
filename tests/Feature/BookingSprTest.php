<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Unit;
use App\Models\Project;
use App\Models\UnitType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingSprTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_spr_template_saves_all_customizations_without_error()
    {
        $user = User::factory()->create();
        $lead = Lead::create(['name' => 'John Doe', 'phone' => '08123456789']);
        $project = Project::create(['name' => 'Umala Project', 'code' => 'UML', 'location' => 'Jakarta', 'status' => 'active']);
        $unitType = UnitType::create(['project_id' => $project->id, 'name' => 'Type 36', 'land_area' => 72, 'building_area' => 36]);
        $unit = Unit::create([
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '1',
            'status' => 'available',
            'certificate_status' => 'SHM',
            'certificate_number' => 'SHM-12345'
        ]);

        $booking = Booking::create([
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'project_id' => $project->id,
            'spk_number' => '001/SPR-TEST/2026',
            'booking_fee' => 10000000,
            'unit_price' => 500000000,
            'final_price' => 500000000,
            'booking_date' => now(),
            'payment_scheme' => 'kpr',
            'status' => 'approved',
            'booked_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->post("/bookings/{$booking->id}/spr-template", [
            'spk_number' => '002/SPR-EDITED/2026',
            'secondary_name' => 'Fatimah',
            'secondary_nik' => '3171012345678901',
            'secondary_npwp' => '99.888.777.6-543.000',
            'secondary_email' => '',
            'spr_date' => '',
            'unit_certificate_status' => 'SHM',
            'unit_certificate_number' => 'SHM-99999',
            'spr_special_offer' => [
                'enabled' => true,
                'title' => 'Special Offer Promo Merdeka',
                'promo_valid_until' => '31 Agustus 2026'
            ],
            'special_bonus_items' => ['Kitchen Set', 'AC 1PK'],
            'special_package_items' => ['Free BPHTB', 'Free AJB'],
            'spr_schedule_dates' => [
                'utj_date' => '25 Agustus 2026',
                'dp_date' => '25 September 2026',
                'installment_date' => 'Saat Akad Kredit'
            ]
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $booking->refresh();
        $unit->refresh();

        $this->assertEquals('Fatimah', $booking->secondary_name);
        $this->assertEquals('99.888.777.6-543.000', $booking->secondary_npwp);
        $this->assertNull($booking->spr_date);
        $this->assertEquals('SHM', $unit->certificate_status);
        $this->assertEquals('SHM-99999', $unit->certificate_number);
        $this->assertEquals(['Kitchen Set', 'AC 1PK'], $booking->special_bonus_items);
        $this->assertEquals(['Free BPHTB', 'Free AJB'], $booking->special_package_items);
        $this->assertEquals('Special Offer Promo Merdeka', $booking->spr_special_offer['title']);
    }
}
