<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\PaymentSchedule;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\InstagramLeadCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptAndCleanupEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private function createProjectUnit(Company $company, string $projectCode = 'ALC', string $block = 'B', string $number = '2', string $status = 'available'): array
    {
        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Alonica Hills ' . $block,
            'code' => $projectCode,
            'location' => 'Jakarta Selatan',
            'status' => 'active',
        ]);

        $unitType = UnitType::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Alonica Standard',
            'land_area' => 105,
            'building_area' => 198,
            'base_price' => 1500000000,
            'current_price' => 1500000000,
        ]);

        $unit = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => $block,
            'number' => $number,
            'status' => $status,
            'final_price' => 1500000000,
        ]);

        return [$project, $unitType, $unit];
    }

    public function test_reservation_kwitansi_full_end_to_end_lifecycle(): void
    {
        Storage::fake('public');

        $company = Company::create(['name' => 'PT Serangkai Roden Development', 'slug' => 'serangkai-test', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active', 'agent_type' => 'master_lead']);
        $this->actingAs($user);

        Setting::updateOrCreate(
            ['company_id' => $company->id, 'key' => 'company_name'],
            ['value' => 'PT Serangkai Roden Development']
        );

        [$project, $unitType, $unitA] = $this->createProjectUnit($company, 'ALC', 'B', '2', 'available');
        $unitB = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'C',
            'number' => '5',
            'status' => 'available',
            'final_price' => 1600000000,
        ]);
        $soldUnit = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'Z',
            'number' => '99',
            'status' => 'sold',
            'final_price' => 1600000000,
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Muhamad Tauhid',
            'phone' => '08558880808',
            'email' => '',
            'identity_number' => '3173071006890004',
            'source' => 'inhouse',
            'status' => 'new',
            'assigned_to' => $user->id,
        ]);

        // 1. Create form renders cleanly (tests driver-aware unit sorting & default company name)
        $this->get('/reservations/create')->assertStatus(200);
        $this->get("/leads/{$lead->id}")->assertStatus(200);

        // 2. Reject sold unit cleanly without leaving partial records
        $soldResponse = $this->post('/reservations', [
            'unit_id' => $soldUnit->id,
            'lead_id' => $lead->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => '11000000',
            'payment_method' => 'transfer',
        ]);
        $soldResponse->assertRedirect();
        $soldResponse->assertSessionHas('error');
        $this->assertEquals(0, Reservation::count());

        // 3. Create reservation with multipart form strings + file proof (mirrors browser FormData)
        $storeResponse = $this->post('/reservations', [
            'unit_id' => (string) $unitA->id,
            'lead_id' => (string) $lead->id,
            'negotiation_id' => '',
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'client_email' => '',
            'client_nik' => $lead->identity_number,
            'amount' => '11.000.000',
            'payment_method' => 'transfer',
            'payment_proof' => UploadedFile::fake()->image('bukti-utj.png'),
            'company_name' => 'PT Serangkai Roden Development',
            'agent_coordinator_id' => (string) $user->id,
            'expires_days' => '7',
            'notes' => 'B2 costums',
        ]);

        $reservation = Reservation::firstOrFail();
        $storeResponse->assertRedirect(route('reservations.show', $reservation->id));
        $storeResponse->assertSessionHasNoErrors();

        $this->assertEquals(11000000, $reservation->amount);
        $this->assertEquals($company->id, $reservation->company_id);
        $this->assertNotNull($reservation->payment_proof);
        Storage::disk('public')->assertExists($reservation->payment_proof);

        $unitA->refresh();
        $lead->refresh();
        $this->assertEquals('reserved', $unitA->status);
        $this->assertEquals('reservation', $lead->status);

        // 4. Detail page loads cleanly
        $this->get("/reservations/{$reservation->id}")->assertStatus(200);

        // 5. Update receipt customization & amount
        $updateResponse = $this->put("/reservations/{$reservation->id}", [
            'client_name' => 'Muhamad Tauhid Updated',
            'client_phone' => '08558880808',
            'client_email' => '',
            'client_nik' => '3173071006890004',
            'amount' => '12.500.000',
            'payment_method' => 'transfer',
            'company_name' => 'PT Serangkai Roden Development',
            'receipt_title' => 'KWITANSI RESMI UTJ',
            'city' => 'Jakarta Selatan',
            'custom_overrides' => [
                'project_name' => $project->name,
                'unit_code' => 'Blok B No. 2',
                'unit_type_name' => $unitType->name,
                'spec_text' => 'LB 198 m² / LT 105 m²',
            ],
            'agent_coordinator_id' => '',
            'agent_coordinator_name' => $user->name,
            'agent_coordinator_title' => 'Sales Coordinator',
        ]);
        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertEquals(12500000, $reservation->amount);
        $this->assertEquals('KWITANSI RESMI UTJ', $reservation->receipt_title);

        // 6. Move unit (change-unit) releases old unit, locks new unit, and clears stale unit overrides
        $moveResponse = $this->post("/reservations/{$reservation->id}/change-unit", [
            'unit_id' => $unitB->id,
            'reason' => 'Pindah ke Blok C',
        ]);
        $moveResponse->assertRedirect();
        $moveResponse->assertSessionHasNoErrors();

        $unitA->refresh();
        $unitB->refresh();
        $reservation->refresh();
        $this->assertEquals('available', $unitA->status);
        $this->assertEquals('reserved', $unitB->status);
        $this->assertEquals($unitB->id, $reservation->unit_id);
        $this->assertArrayNotHasKey('unit_code', $reservation->custom_overrides ?? []);

        // 7. Stream HTML & real PDF receipt
        $htmlReceipt = $this->get("/reservations/{$reservation->id}/receipt?html=1");
        $htmlReceipt->assertStatus(200);
        $htmlReceipt->assertSee($reservation->reservation_number);
        $htmlReceipt->assertSee('Muhamad Tauhid Updated');
        $htmlReceipt->assertSee('Blok C No. 5');
        $htmlReceipt->assertSee('12.500.000');

        $pdfReceipt = $this->get("/reservations/{$reservation->id}/receipt");
        $pdfReceipt->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $pdfReceipt->getContent());

        // 8. Convert link & /bookings/create work seamlessly
        $this->get("/reservations/{$reservation->id}/convert")
            ->assertRedirect(route('bookings.create', [
                'reservation_id' => $reservation->id,
                'unit_id' => $unitB->id,
                'lead_id' => $lead->id,
                'reserved_amount' => $reservation->amount,
            ]));
        $this->get('/bookings/create?reservation_id=' . $reservation->id)->assertStatus(200);

        // 9. Process 100% Refund releases unit back to available
        $refundResponse = $this->post("/reservations/{$reservation->id}/refund", [
            'refund_bank_name' => 'BCA',
            'refund_account_number' => '1234567890',
            'refund_account_name' => 'Muhamad Tauhid',
            'refund_reason' => 'Batal karena penyesuaian skema',
        ]);
        $refundResponse->assertRedirect();
        $reservation->refresh();
        $unitB->refresh();
        $this->assertEquals('refunded', $reservation->status);
        $this->assertEquals(12500000, $reservation->refund_amount);
        $this->assertEquals('available', $unitB->status);
    }

    public function test_multi_tenant_reservation_number_generation_does_not_collide(): void
    {
        $companyA = Company::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha', 'status' => 'active']);
        $companyB = Company::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta', 'status' => 'active']);

        $userA = User::factory()->create(['company_id' => $companyA->id, 'status' => 'active']);
        $userB = User::factory()->create(['company_id' => $companyB->id, 'status' => 'active']);

        [, , $unitA] = $this->createProjectUnit($companyA, 'ALCA', 'A', '1', 'available');
        [, , $unitB] = $this->createProjectUnit($companyB, 'ALCB', 'A', '1', 'available');

        // Force both tenants to generate numbers from the exact same prefix format
        Setting::updateOrCreate(
            ['company_id' => $companyA->id, 'key' => 'reservation_number_format'],
            ['value' => '{seq}/RSV-SHARED/{month_roman}/{year}']
        );
        Setting::updateOrCreate(
            ['company_id' => $companyB->id, 'key' => 'reservation_number_format'],
            ['value' => '{seq}/RSV-SHARED/{month_roman}/{year}']
        );

        $this->actingAs($userA)->post('/reservations', [
            'unit_id' => $unitA->id,
            'client_name' => 'Client Alpha',
            'client_phone' => '08111111111',
            'amount' => 10000000,
            'payment_method' => 'transfer',
        ])->assertSessionHasNoErrors();

        $this->actingAs($userB)->post('/reservations', [
            'unit_id' => $unitB->id,
            'client_name' => 'Client Beta',
            'client_phone' => '08222222222',
            'amount' => 10000000,
            'payment_method' => 'transfer',
        ])->assertSessionHasNoErrors();

        $numbers = Reservation::withoutGlobalScopes()->pluck('reservation_number')->all();
        $this->assertCount(2, $numbers);
        $this->assertNotEquals($numbers[0], $numbers[1]);
    }

    public function test_finance_payment_receipt_end_to_end_lifecycle(): void
    {
        Storage::fake('public');

        $company = Company::create(['name' => 'PT Finance Receipt Test', 'slug' => 'finance-receipt-test', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'HRT', 'A', '7', 'booked');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Konsumen Kwitansi Finance',
            'phone' => '08134567890',
            'source' => 'referral',
            'status' => 'booking',
        ]);

        $bank = BankAccount::create([
            'company_id' => $company->id,
            'name' => 'Operasional Utama',
            'bank_name' => 'BCA',
            'account_number' => '8880001112',
            'account_holder' => 'PT Finance Receipt Test',
            'is_active' => true,
        ]);

        $booking = Booking::create([
            'company_id' => $company->id,
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'project_id' => $project->id,
            'spk_number' => '001/SPR-HRT/2026',
            'booking_fee' => 11000000,
            'unit_price' => 1000000000,
            'final_price' => 1000000000,
            'booking_date' => now(),
            'payment_scheme' => 'cash_installment',
            'status' => 'approved',
            'booked_by' => $user->id,
            'bank_account_id' => $bank->id,
        ]);

        $schedule = PaymentSchedule::create([
            'company_id' => $company->id,
            'booking_id' => $booking->id,
            'installment_number' => 0,
            'label' => 'Uang Tanda Jadi (UTJ)',
            'amount' => 11000000,
            'due_date' => now()->toDateString(),
            'status' => 'due',
        ]);

        $this->get('/finance/create')->assertStatus(200);

        $txResponse = $this->post('/transactions', [
            'booking_id' => $booking->id,
            'payment_schedule_id' => $schedule->id,
            'amount' => 11000000,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'bank_name' => 'BCA',
            'bank_account_id' => $bank->id,
            'notes' => 'Pembayaran UTJ Konsumen',
        ]);
        $txResponse->assertRedirect();
        $txResponse->assertSessionHasNoErrors();

        $tx = Transaction::firstOrFail();
        $schedule->refresh();
        $this->assertEquals('paid', $schedule->status);

        // Open Finance Receipt page
        $this->get("/finance/transactions/{$tx->id}/receipt")->assertStatus(200);

        // Edit receipt metadata
        $editTxResponse = $this->put("/finance/transactions/{$tx->id}", [
            'payment_method' => 'transfer',
            'receipt_number' => '001/KWT-HRT/2026',
            'bank_account_text' => 'BCA - 8880001112 a.n. PT Finance Receipt Test',
            'payment_date' => now()->toDateString(),
        ]);
        $editTxResponse->assertRedirect();
        $editTxResponse->assertSessionHasNoErrors();

        $tx->refresh();
        $booking->refresh();
        $this->assertEquals('001/KWT-HRT/2026', $tx->receipt_number);
        $this->assertEquals('001/KWT-HRT/2026', $booking->receipt_settings['receipt_number_custom']);

        // Upload wet receipt scan
        $wetResponse = $this->post("/finance/transactions/{$tx->id}/wet-receipt", [
            'wet_receipt_file' => UploadedFile::fake()->image('kwitansi-basah.png'),
        ]);
        $wetResponse->assertRedirect();
        $tx->refresh();
        $this->assertNotNull($tx->wet_receipt_file);
        Storage::disk('public')->assertExists($tx->wet_receipt_file);
    }

    public function test_instagram_cleanup_deletes_only_unlinked_instagram_leads_and_preserves_all_other_data(): void
    {
        $company = Company::create(['name' => 'Cleanup Safety Co', 'slug' => 'cleanup-safety', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'CSG', 'D', '1', 'reserved');

        // A. Pure Meta Instagram Lead Ads / DM Lead (unlinked) -> MUST be deleted
        $igAutoLead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'IG Auto Spam',
            'phone' => '08190000001',
            'source' => 'instagram',
            'notes' => '[Via Meta Lead Ads Form] Form ID: 999',
            'status' => 'new',
        ]);
        LeadActivity::create([
            'company_id' => $company->id,
            'lead_id' => $igAutoLead->id,
            'user_id' => $user->id,
            'type' => 'note',
            'description' => 'Auto lead activity',
        ]);
        DB::table('chat_messages')->insert([
            'company_id' => $company->id,
            'lead_id' => $igAutoLead->id,
            'platform' => 'instagram',
            'direction' => 'incoming',
            'phone' => 'ig_sender_1',
            'message' => 'Halo dari IG',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('chat_messages')->insert([
            'company_id' => $company->id,
            'lead_id' => null,
            'platform' => 'instagram',
            'direction' => 'incoming',
            'phone' => 'ig_orphan',
            'message' => 'Pesan IG orphan',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // B. WhatsApp lead whose source happened to say instagram -> MUST be preserved
        $waLead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'WA Konsumen Asli',
            'phone' => '08190000002',
            'source' => 'instagram',
            'notes' => '[Via WhatsApp API] Tanya promo rumah',
            'status' => 'follow_up',
        ]);

        // C. Non-Instagram manual lead -> MUST be preserved
        $manualLead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Manual Walk In',
            'phone' => '08190000003',
            'source' => 'referral',
            'status' => 'qualified',
        ]);

        // D. Instagram lead with active Reservation -> MUST be preserved
        $igReservedLead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'IG Sudah Reservasi',
            'phone' => '08190000004',
            'source' => 'instagram',
            'notes' => '[Via Meta Instagram Chat] Sender: Bima',
            'status' => 'reservation',
        ]);
        Reservation::create([
            'company_id' => $company->id,
            'reservation_number' => '901/RSV-CSG/XI/2026',
            'project_id' => $project->id,
            'unit_id' => $unit->id,
            'lead_id' => $igReservedLead->id,
            'client_name' => $igReservedLead->name,
            'client_phone' => $igReservedLead->phone,
            'amount' => 11000000,
            'payment_method' => 'transfer',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $deleted = InstagramLeadCleanupService::cleanup(true, false);

        $this->assertEquals(1, $deleted['leads']);
        $this->assertEquals(2, $deleted['chat_messages']);
        $this->assertEquals(1, $deleted['lead_activities']);

        $this->assertDatabaseMissing('leads', ['id' => $igAutoLead->id]);
        $this->assertDatabaseHas('leads', ['id' => $waLead->id]);
        $this->assertDatabaseHas('leads', ['id' => $manualLead->id]);
        $this->assertDatabaseHas('leads', ['id' => $igReservedLead->id]);
        $this->assertDatabaseHas('reservations', ['lead_id' => $igReservedLead->id]);
    }

    public function test_updating_sub_agent_with_null_commission_rate_and_inhouse_master_lead_succeeds(): void
    {
        $company = Company::create(['name' => 'Master Lead Update Test Co', 'slug' => 'ml-update-test', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $masterLead = User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'agent_type' => 'master_lead',
        ]);
        $subAgent = User::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'agent_type' => 'agency_agent',
            'master_lead_id' => $masterLead->id,
            'commission_rate' => 2.5,
        ]);

        // Reproduces exact MasterLeads/Index.vue PUT /users/{id} payload where blank fee sends null/0
        $response = $this->actingAs($admin)->put("/users/{$subAgent->id}", [
            'name' => 'Sub Agent Updated',
            'phone' => '081234567890',
            'email' => $subAgent->email,
            'role' => 'sales_agent',
            'agent_type' => 'inhouse_master_lead',
            'broker_company_id' => null,
            'master_lead_id' => $masterLead->id,
            'commission_rate' => null,
            'custom_bonus' => 0,
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_name' => 'Sub Agent Updated',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $subAgent->refresh();
        $this->assertEquals('Sub Agent Updated', $subAgent->name);
        $this->assertEquals('inhouse_master_lead', $subAgent->agent_type);
        $this->assertEquals(0.0, (float) $subAgent->commission_rate);
    }
}
