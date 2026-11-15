<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Negotiation;
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
use Inertia\Testing\AssertableInertia;
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

    public function test_standard_booking_fee_setting_drives_reservation_remaining_utj_and_booking_flow(): void
    {
        $company = Company::create(['name' => 'Parameter Booking Co', 'slug' => 'param-booking-co', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        // Default before setting saved should be 17,000,000
        $this->assertEquals(17000000, Setting::getStandardBookingFee());

        [$project, , $unit] = $this->createProjectUnit($company, 'PBC', 'A', '10', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Konsumen Parameter Booking',
            'phone' => '08177778888',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        // 1. Update fixed standard_booking_fee in /settings to Rp 20.000.000
        $this->get('/settings')->assertStatus(200);
        $this->post('/settings', [
            'standard_booking_fee' => '20.000.000',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertEquals(20000000, Setting::getStandardBookingFee());

        // 2. Create manual reservation of Rp 11.000.000
        $this->post('/reservations', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => 11000000,
            'payment_method' => 'transfer',
        ])->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();

        // 3. Receipt HTML/PDF reflects Rp 20.000.000 - Rp 11.000.000 = Rp 9.000.000
        $htmlPartial = $this->get("/reservations/{$reservation->id}/receipt?html=1");
        $htmlPartial->assertStatus(200);
        $htmlPartial->assertSee('Rp 20.000.000');
        $htmlPartial->assertSee('Rp 11.000.000');
        $htmlPartial->assertSee('Rp 9.000.000');

        // 4. Verify pages pass standardBookingFee prop cleanly
        $this->get('/reservations/create')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('standardBookingFee', 20000000));
        $this->get("/reservations/{$reservation->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('standardBookingFee', 20000000));
        $this->get("/leads/{$lead->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('standardBookingFee', 20000000));
        $this->get("/bookings/create?reservation_id={$reservation->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page->where('standardBookingFee', 20000000));

        // 5. Update standard_booking_fee to Rp 11.000.000 (promo matches manual reservation) -> Rp 0 remaining
        $this->post('/settings', [
            'standard_booking_fee' => 11000000,
        ])->assertRedirect();

        $htmlSettled = $this->get("/reservations/{$reservation->id}/receipt?html=1");
        $htmlSettled->assertStatus(200);
        $htmlSettled->assertSee('Rp 11.000.000');
        $htmlSettled->assertSee('Rp 0 (UTJ Standar Terpenuhi Penuh)');
    }

    public function test_developer_signatory_roles_flow_end_to_end_from_settings_to_receipts_and_booking(): void
    {
        Storage::fake('public');

        $company = Company::create(['name' => 'PT Signatory Developer', 'slug' => 'signatory-dev-co', 'status' => 'active']);
        $admin = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $salesAgent = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Rudi Closing Agent',
            'agent_type' => 'inhouse',
            'status' => 'active',
        ]);

        [$project, , $unit] = $this->createProjectUnit($company, 'ALC', 'B', '7', 'available');

        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Dewi Sartika',
            'phone' => '081211122233',
            'status' => 'negotiation',
            'source' => 'inhouse',
            'assigned_to' => $salesAgent->id,
        ]);

        $this->actingAs($admin);

        // 1. Configure Developer Signatories (including Finance role + TTD upload + reservation default slot) in /settings
        $this->post('/settings', [
            'spr_signatures' => [
                'city' => 'Jakarta Selatan',
                'sig1_title' => 'Sales Manager',
                'sig1_name' => 'Dhany Nur',
                'sig2_title' => 'Direktur',
                'sig2_name' => 'Luhur Wira Pramudya',
                'finance_title' => 'Finance & Kasir',
                'finance_name' => 'Siti Aminah Finance',
                'reservation_signer_slot' => 'finance',
                'sig3_title' => 'SALES',
                'sig4_title' => 'Pemesan / Pembeli',
            ],
            'signature_image_finance' => UploadedFile::fake()->image('ttd-finance.png'),
        ])->assertRedirect();

        $sigConfig = Setting::getSignatureConfig();
        $this->assertEquals('finance', $sigConfig['reservation_signer_slot']);
        $this->assertEquals('Siti Aminah Finance', $sigConfig['finance_name']);
        $this->assertNotEmpty($sigConfig['finance_image']);
        Storage::disk('public')->assertExists($sigConfig['finance_image']);

        // 2. Verify pages expose developerSignatories & defaultDeveloperSigner props cleanly
        $this->get('/reservations/create')
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('defaultDeveloperSigner.slot', 'finance')
                ->where('defaultDeveloperSigner.name', 'Siti Aminah Finance')
                ->where('developerSignatories.0.key', 'finance')
            );

        $this->get("/leads/{$lead->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('defaultDeveloperSigner.slot', 'finance')
                ->where('defaultDeveloperSigner.name', 'Siti Aminah Finance')
            );

        // 3. Create reservation without manual signer override -> uses Finance signatory while crediting Sales PIC separately
        $this->post('/reservations', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => 11000000,
            'payment_method' => 'transfer',
        ])->assertSessionHasNoErrors();

        $reservation = Reservation::firstOrFail();
        $this->assertEquals('Siti Aminah Finance', $reservation->agent_coordinator_name);
        $this->assertEquals('Finance & Kasir', $reservation->agent_coordinator_title);
        $this->assertEquals('finance', $reservation->custom_overrides['signer_slot'] ?? null);
        $this->assertEquals($sigConfig['finance_image'], $reservation->custom_overrides['signer_image'] ?? null);
        $this->assertEquals('Rudi Closing Agent', $reservation->custom_overrides['sales_pic_name'] ?? null);

        $htmlFinanceReceipt = $this->get("/reservations/{$reservation->id}/receipt?html=1");
        $htmlFinanceReceipt->assertStatus(200);
        $htmlFinanceReceipt->assertSee('Siti Aminah Finance');
        $htmlFinanceReceipt->assertSee('Finance &amp; Kasir', false);
        $htmlFinanceReceipt->assertSee('Rudi Closing Agent');

        // 4. Switch reservation signer to Manajemen / Direktur (sig2) via PUT
        $this->put("/reservations/{$reservation->id}", [
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => 11000000,
            'payment_method' => 'transfer',
            'signer_slot' => 'sig2',
            'sales_pic_name' => 'Rudi Closing Agent',
        ])->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertEquals('Luhur Wira Pramudya', $reservation->agent_coordinator_name);
        $this->assertEquals('Direktur', $reservation->agent_coordinator_title);
        $this->assertEquals('sig2', $reservation->custom_overrides['signer_slot'] ?? null);

        // 5. Convert / store Booking -> inherits configured Developer Signatory defaults & SPK PDF shows Pembeli (not SALES)
        $this->post('/bookings', [
            'reservation_id' => $reservation->id,
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'booked_by' => $salesAgent->id,
            'booking_fee' => 17000000,
            'base_price' => 1500000000,
            'final_price' => 1500000000,
            'payment_scheme' => 'kpr',
            'dp_amount' => 150000000,
        ])->assertSessionHasNoErrors();

        $booking = \App\Models\Booking::firstOrFail();
        $this->assertEquals('Dhany Nur', $booking->sig1_name);
        $this->assertEquals('Sales Manager', $booking->sig1_title);
        $this->assertEquals('Luhur Wira Pramudya', $booking->sig2_name);
        $this->assertEquals('Direktur', $booking->sig2_title);
        $this->assertEquals('Rudi Closing Agent', $booking->sig3_name);
        $this->assertEquals('SALES', $booking->sig3_title);

        $this->get("/bookings/{$booking->id}")
            ->assertStatus(200)
            ->assertInertia(fn ($page) => $page
                ->where('signatureConfig.finance_name', 'Siti Aminah Finance')
                ->where('signatureConfig.reservation_signer_slot', 'finance')
            );
    }

    public function test_custom_terms_split_dp_persists_through_pending_approve_spr_and_spk(): void
    {
        $company = Company::create(['name' => 'Custom Terms Co', 'slug' => 'custom-terms-co', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'CTC', 'B', '1', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Buyer Pecah Termin',
            'phone' => '08199990000',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        // Harga 3 M, UTJ 11 jt -> Sisa 2.989.000.000 dipecah menjadi 3 termin berbeda nominal
        $response = $this->post('/bookings', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'booked_by' => $user->id,
            'booking_fee' => 11000000,
            'base_price' => 3000000000,
            'final_price' => 3000000000,
            'payment_scheme' => 'cash_installment',
            'schedule_mode' => 'custom_terms',
            'booking_date' => '2026-11-15',
            'custom_schedules' => [
                ['label' => 'DP 1', 'amount' => 200000000, 'due_date' => '2026-12-15'],
                ['label' => 'DP 2', 'amount' => 1700000000, 'due_date' => '2027-01-15'],
                ['label' => 'DP 3 (Pelunasan)', 'amount' => 1089000000, 'due_date' => '2027-02-15'],
            ],
        ]);

        $response->assertRedirect()->assertSessionHasNoErrors();

        $booking = \App\Models\Booking::where('lead_id', $lead->id)->firstOrFail();
        $this->assertEquals('pending', $booking->status);
        $this->assertEquals(2989000000.0, (float) $booking->dp_amount);
        $this->assertEquals(3, (int) $booking->dp_installment_months);
        $this->assertEquals(0, (int) $booking->installment_months);

        $schedules = $booking->paymentSchedules()->orderBy('installment_number')->get();
        $this->assertCount(4, $schedules);
        $this->assertEquals(0, $schedules[0]->installment_number);
        $this->assertEquals('upcoming', $schedules[0]->status);
        $this->assertEquals('DP 1', $schedules[1]->label);
        $this->assertEquals(200000000.0, (float) $schedules[1]->amount);
        $this->assertEquals('DP 2', $schedules[2]->label);
        $this->assertEquals(1700000000.0, (float) $schedules[2]->amount);
        $this->assertEquals('DP 3 (Pelunasan)', $schedules[3]->label);
        $this->assertEquals(1089000000.0, (float) $schedules[3]->amount);
        $this->assertEquals(3000000000.0, (float) $schedules->sum('amount'));

        // Approve must mark UTJ paid WITHOUT wiping custom DP 1, DP 2, DP 3
        $this->post("/bookings/{$booking->id}/approve")->assertSessionHasNoErrors();
        $booking->refresh();
        $this->assertEquals('approved', $booking->status);

        $approvedSchedules = $booking->paymentSchedules()->orderBy('installment_number')->get();
        $this->assertCount(4, $approvedSchedules);
        $this->assertEquals('paid', $approvedSchedules[0]->status);
        $this->assertEquals(200000000.0, (float) $approvedSchedules[1]->amount);
        $this->assertEquals(1700000000.0, (float) $approvedSchedules[2]->amount);
        $this->assertEquals(1089000000.0, (float) $approvedSchedules[3]->amount);
        $this->assertEquals(3000000000.0, (float) $approvedSchedules->sum('amount'));

        // SPR HTML must display the exact 3 custom terms (not 66,666,667 or 60 Bulan)
        $sprHtml = $this->get("/bookings/{$booking->id}/spr?html=1");
        $sprHtml->assertStatus(200);
        $sprHtml->assertSee('Booking Fee (UTJ)');
        $sprHtml->assertSee('200,000,000.00');
        $sprHtml->assertSee('1,700,000,000.00');
        $sprHtml->assertSee('DP 3 (Pelunasan)');
        $sprHtml->assertSee('1,089,000,000.00');
        $sprHtml->assertSee('3,000,000,000.00');
        $sprHtml->assertDontSee('60 Bulan');

        // SPK Blade view must also reflect actual paymentSchedules rows
        $spkRendered = view('pdf.spk', ['booking' => $booking->load(['unit.project', 'unit.unitType', 'lead', 'bookedBy', 'paymentSchedules']), 'settings' => []])->render();
        $this->assertStringContainsString('DP 1', $spkRendered);
        $this->assertStringContainsString('200,000,000.00', $spkRendered);
        $this->assertStringContainsString('1,700,000,000.00', $spkRendered);
        $this->assertStringContainsString('DP 3 (Pelunasan)', $spkRendered);
        $this->assertStringContainsString('1,089,000,000.00', $spkRendered);
    }

    public function test_custom_terms_rejects_mismatched_total_to_prevent_inconsistency(): void
    {
        $company = Company::create(['name' => 'Custom Mismatch Co', 'slug' => 'custom-mismatch-co', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'CMC', 'C', '2', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Buyer Selisih',
            'phone' => '08188887777',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        $response = $this->post('/bookings', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'booked_by' => $user->id,
            'booking_fee' => 11000000,
            'base_price' => 3000000000,
            'final_price' => 3000000000,
            'payment_scheme' => 'cash_installment',
            'schedule_mode' => 'custom_terms',
            'custom_schedules' => [
                ['label' => 'DP 1', 'amount' => 200000000],
                ['label' => 'DP 2', 'amount' => 500000000],
            ],
        ]);

        $response->assertSessionHasErrors(['custom_schedules']);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_standard_zero_installment_months_and_regenerate_custom_preserve_paid_and_total(): void
    {
        $company = Company::create(['name' => 'Standard Zero Co', 'slug' => 'std-zero-co', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'SZC', 'D', '5', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Buyer Zero Tenor',
            'phone' => '08177776666',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        // Standard cash_installment with 200jt DP (2x) and installment_months = 0 on 1 M unit (10jt UTJ)
        $this->post('/bookings', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'booked_by' => $user->id,
            'booking_fee' => 10000000,
            'base_price' => 1000000000,
            'final_price' => 1000000000,
            'payment_scheme' => 'cash_installment',
            'schedule_mode' => 'standard',
            'dp_amount' => 200000000,
            'dp_installment_months' => 2,
            'installment_months' => 0,
            'auto_approve' => true,
        ])->assertSessionHasNoErrors();

        $booking = \App\Models\Booking::where('lead_id', $lead->id)->firstOrFail();
        $rows = $booking->paymentSchedules()->orderBy('installment_number')->get();

        // UTJ 10jt + DP 1 100jt + DP 2 100jt + Pelunasan Sisa Cash Bertahap 790jt = 1M
        $this->assertCount(4, $rows);
        $this->assertEquals('Pelunasan Sisa Cash Bertahap', $rows[3]->label);
        $this->assertEquals(790000000.0, (float) $rows[3]->amount);
        $this->assertEquals(1000000000.0, (float) $rows->sum('amount'));

        // Mark DP 1 (100jt) as paid, then regenerate remaining 890jt into 2 custom terms
        $dp1Row = $rows[1];
        $this->put("/payment-schedules/{$dp1Row->id}", [
            'label' => $dp1Row->label,
            'amount' => $dp1Row->amount,
            'due_date' => $dp1Row->due_date->format('Y-m-d'),
            'status' => 'paid',
        ])->assertSessionHasNoErrors();

        $this->post("/bookings/{$booking->id}/regenerate-schedule", [
            'payment_scheme' => 'cash_installment',
            'schedule_mode' => 'custom_terms',
            'custom_schedules' => [
                ['label' => 'DP 2 Besar', 'amount' => 500000000, 'due_date' => '2027-01-10'],
                ['label' => 'Pelunasan Akhir', 'amount' => 390000000, 'due_date' => '2027-02-10'],
            ],
        ])->assertSessionHasNoErrors();

        $afterRegen = $booking->paymentSchedules()->orderBy('installment_number')->get();
        $this->assertCount(4, $afterRegen);
        $this->assertEquals('paid', $afterRegen[0]->status);
        $this->assertEquals('paid', $afterRegen[1]->status);
        $this->assertEquals('DP 2 Besar', $afterRegen[2]->label);
        $this->assertEquals(500000000.0, (float) $afterRegen[2]->amount);
        $this->assertEquals('Pelunasan Akhir', $afterRegen[3]->label);
        $this->assertEquals(390000000.0, (float) $afterRegen[3]->amount);
        $this->assertEquals(1000000000.0, (float) $afterRegen->sum('amount'));
    }

    public function test_booking_store_decimal_strings_spr_template_and_dompdf_render_end_to_end(): void
    {
        $company = Company::create(['name' => 'SPR E2E Dev', 'slug' => 'spr-e2e-dev', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, , $unit] = $this->createProjectUnit($company, 'E2E', 'A', '10', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Buyer SPR Lengkap',
            'phone' => '08188889999',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        $response = $this->post('/bookings', [
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'booked_by' => $user->id,
            'booking_fee' => '17000000.00',
            'base_price' => '2850000000.00',
            'ppn_amount' => '0.00',
            'bphtb_amount' => '0.00',
            'ajb_bbn_amount' => '0.00',
            'other_legal_fees' => '0.00',
            'final_price' => '2850000000.00',
            'payment_scheme' => 'cash_installment',
            'schedule_mode' => 'custom_terms',
            'custom_schedules' => [
                ['label' => 'DP 1', 'amount' => '200000000.00', 'due_date' => '2026-12-10'],
                ['label' => 'DP 2', 'amount' => '1700000000.00', 'due_date' => '2027-01-10'],
                ['label' => 'DP 3 (Pelunasan)', 'amount' => '933000000.00', 'due_date' => '2027-02-10'],
            ],
            'auto_approve' => true,
            'redirect_to' => 'lead',
        ]);

        $response->assertRedirect(route('leads.show', $lead->id));
        $response->assertSessionHasNoErrors();

        $booking = \App\Models\Booking::where('lead_id', $lead->id)->firstOrFail();
        $this->assertEquals(2850000000.0, (float) $booking->final_price);
        $this->assertEquals(17000000.0, (float) $booking->booking_fee);
        $this->assertEquals('approved', $booking->status);
        $originalSpk = $booking->spk_number;

        // Follow redirect targets without 500
        $this->get(route('leads.show', $lead->id))->assertStatus(200);
        $this->get(route('bookings.show', $booking->id))->assertStatus(200);

        // Save SPR template with empty spk_number -> must NOT throw 500 or overwrite spk_number with null
        $this->post("/bookings/{$booking->id}/spr-template", [
            'spk_number' => '',
            'sigs_city' => 'Jakarta Selatan',
            'buyer_nik' => '3173071006890004',
            'unit_certificate_status' => 'SHM',
            'sync_schedules' => false,
        ])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertEquals($originalSpk, $booking->spk_number);
        $this->assertEquals('Jakarta Selatan', $booking->sigs_city);

        // SPR HTML, inline PDF (?pdf=1), SPR download, and SPK download via DomPDF
        $htmlRes = $this->get("/bookings/{$booking->id}/spr");
        $htmlRes->assertStatus(200);
        $htmlRes->assertSee('Buyer SPR Lengkap');
        $htmlRes->assertSee('DP 3 (Pelunasan)');

        $pdfInline = $this->get("/bookings/{$booking->id}/spr?pdf=1");
        $pdfInline->assertStatus(200);
        $this->assertStringContainsString('%PDF-', $pdfInline->getContent());

        $pdfDownload = $this->get("/bookings/{$booking->id}/spr/download");
        $pdfDownload->assertStatus(200);

        $spkDownload = $this->get("/bookings/{$booking->id}/spk");
        $spkDownload->assertStatus(200);
    }

    public function test_spr_rendering_handles_corrupt_settings_and_missing_relations_without_500(): void
    {
        $company = Company::create(['name' => 'Corrupt Edge Co', 'slug' => 'corrupt-edge-co', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, $unitType, $unit] = $this->createProjectUnit($company, 'CEC', 'B', '7', 'available');
        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Lead SoftDeleted',
            'phone' => '08199998888',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        $booking = \App\Models\Booking::create([
            'company_id' => $company->id,
            'spk_number' => '001/SPR-CEC/XI/2026',
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'project_id' => $project->id,
            'booked_by' => $user->id,
            'booking_fee' => 15000000,
            'base_price' => 1200000000,
            'final_price' => 1200000000,
            'booking_date' => '2026-11-15',
            'payment_scheme' => 'cash',
            'status' => 'approved',
        ]);

        // Force corrupt non-array settings in DB and remove unitType + soft-delete lead/unit
        \App\Models\Setting::set('spr_terms_conditions', "Ketentuan 1\nKetentuan 2");
        \App\Models\Setting::set('spr_special_offer', 'not-an-array');
        \App\Models\Setting::set('spr_signatures', 'bad-json');
        \App\Models\Setting::set('spr_bank_info', 'bad-json');

        $unitType->delete();
        $unit->delete();
        $lead->delete();

        $resHtml = $this->get("/bookings/{$booking->id}/spr");
        $resHtml->assertStatus(200);
        $resHtml->assertSee('Ketentuan 1');

        $resPdf = $this->get("/bookings/{$booking->id}/spr?pdf=1");
        $resPdf->assertStatus(200);
        $this->assertStringContainsString('%PDF-', $resPdf->getContent());
    }

    public function test_generate_spr_number_prevents_cross_tenant_collision_and_supports_superadmin(): void
    {
        $companyA = Company::create(['name' => 'Tenant Alpha', 'slug' => 'tenant-alpha', 'status' => 'active']);
        $companyB = Company::create(['name' => 'Tenant Beta', 'slug' => 'tenant-beta', 'status' => 'active']);

        $userA = User::factory()->create(['company_id' => $companyA->id, 'status' => 'active']);
        $userB = User::factory()->create(['company_id' => $companyB->id, 'status' => 'active']);
        $superAdmin = User::factory()->create(['company_id' => null, 'status' => 'active']);

        [$projectA, , $unitA] = $this->createProjectUnit($companyA, 'ALA', 'A', '1', 'available');
        [$projectB, , $unitB] = $this->createProjectUnit($companyB, 'ALB', 'B', '2', 'available');

        $leadA = Lead::create(['company_id' => $companyA->id, 'project_id' => $projectA->id, 'name' => 'Lead A', 'phone' => '08111', 'source' => 'inhouse', 'status' => 'new']);
        $leadB = Lead::create(['company_id' => $companyB->id, 'project_id' => $projectB->id, 'name' => 'Lead B', 'phone' => '08222', 'source' => 'inhouse', 'status' => 'new']);

        $romanMonths = [1=>'I',2=>'II',3=>'III',4=>'IV',5=>'V',6=>'VI',7=>'VII',8=>'VIII',9=>'IX',10=>'X',11=>'XI',12=>'XII'];
        $monthRoman = $romanMonths[(int)date('n')] ?? 'XI';
        $collidingSpk = sprintf('002/SPR-ALB/%s/%s', $monthRoman, date('Y'));

        // Tenant A holds a booking whose spk_number would collide with Tenant B's first generated ALB number
        $this->actingAs($userA);
        $bookingA = \App\Models\Booking::create([
            'company_id' => $companyA->id,
            'spk_number' => $collidingSpk,
            'lead_id' => $leadA->id,
            'unit_id' => $unitA->id,
            'project_id' => $projectA->id,
            'booked_by' => $userA->id,
            'booking_fee' => 10000000,
            'base_price' => 500000000,
            'final_price' => 500000000,
            'booking_date' => date('Y-m-d'),
            'payment_scheme' => 'cash',
            'status' => 'pending',
        ]);

        // Tenant B creates a booking on project ALB -> count across all tenants + uniqueness skips Tenant A's number
        $this->actingAs($userB);
        $this->post('/bookings', [
            'unit_id' => $unitB->id,
            'lead_id' => $leadB->id,
            'booked_by' => $userB->id,
            'booking_fee' => 10000000,
            'base_price' => 600000000,
            'final_price' => 600000000,
            'payment_scheme' => 'cash',
        ])->assertSessionHasNoErrors();

        $bookingB = \App\Models\Booking::withoutGlobalScopes()->where('lead_id', $leadB->id)->firstOrFail();
        $this->assertNotEquals($bookingA->spk_number, $bookingB->spk_number);

        // SuperAdmin (company_id = null) approves Tenant B's booking without 500 & keeps company_id
        $this->actingAs($superAdmin);
        $this->post("/bookings/{$bookingB->id}/approve")->assertSessionHasNoErrors();
        $bookingB->refresh();
        $this->assertEquals('approved', $bookingB->status);
        $this->assertEquals($companyB->id, $bookingB->company_id);

        // Duplicate spk_number validation in updateSprTemplate returns validation error, not 500
        $this->post("/bookings/{$bookingB->id}/spr-template", [
            'spk_number' => $bookingA->spk_number,
        ])->assertSessionHasErrors(['spk_number']);
    }

    public function test_unit_lov_excludes_sold_and_booked_units_across_all_modules_and_blocks_submission(): void
    {
        $company = Company::create(['name' => 'PT Kana Nusantara', 'slug' => 'kana-lov-test', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->actingAs($user);

        [$project, $unitType, $unitAvailable] = $this->createProjectUnit($company, 'KNA', 'A', '1', 'available');
        $unitHold = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '2',
            'status' => 'hold',
            'final_price' => 1500000000,
        ]);
        $unitReserved = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '3',
            'status' => 'reserved',
            'final_price' => 1500000000,
        ]);
        $unitSold = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '4',
            'status' => 'sold',
            'final_price' => 1500000000,
        ]);
        $unitBooked = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '5',
            'status' => 'booked',
            'final_price' => 1500000000,
        ]);
        // Edge case: unit status still says 'available' in DB, but already has an active approved booking
        $unitActiveBooking = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'A',
            'number' => '6',
            'status' => 'available',
            'final_price' => 1500000000,
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Buyer LOV Check',
            'phone' => '081211122233',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        Booking::create([
            'company_id' => $company->id,
            'spk_number' => '001/SPR-KNA/XI/2026',
            'lead_id' => $lead->id,
            'unit_id' => $unitActiveBooking->id,
            'project_id' => $project->id,
            'booked_by' => $user->id,
            'booking_fee' => 17000000,
            'base_price' => 1500000000,
            'final_price' => 1500000000,
            'booking_date' => '2026-11-15',
            'payment_scheme' => 'cash',
            'status' => 'approved',
        ]);

        $negoOpen = Negotiation::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'lead_id' => $lead->id,
            'unit_id' => $unitAvailable->id,
            'created_by' => $user->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'unit_listed_price' => 1500000000,
            'offered_price' => 1450000000,
            'payment_scheme' => 'inhouse',
            'status' => 'pending_review',
        ]);

        $negoOnSold = Negotiation::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'lead_id' => $lead->id,
            'unit_id' => $unitSold->id,
            'created_by' => $user->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'unit_listed_price' => 1500000000,
            'offered_price' => 1400000000,
            'payment_scheme' => 'cash',
            'status' => 'approved',
        ]);

        $expectedSelectableIds = [$unitAvailable->id, $unitHold->id, $unitReserved->id];
        sort($expectedSelectableIds);

        // 1. Negotiations Index LOV
        $this->get('/negotiations')
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds) {
                $ids = collect($page->toArray()['props']['units'])->pluck('id')->sort()->values()->all();
                $this->assertSame($expectedSelectableIds, $ids);
            });

        // 2. Negotiations Show LOV excludes sold/booked for open nego, keeps own sold unit for historical nego
        $this->get("/negotiations/{$negoOpen->id}")
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds) {
                $ids = collect($page->toArray()['props']['units'])->pluck('id')->sort()->values()->all();
                $this->assertSame($expectedSelectableIds, $ids);
            });

        $this->get("/negotiations/{$negoOnSold->id}")
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds, $unitSold) {
                $ids = collect($page->toArray()['props']['units'])->pluck('id')->sort()->values()->all();
                $withOwn = array_merge($expectedSelectableIds, [$unitSold->id]);
                sort($withOwn);
                $this->assertSame($withOwn, $ids);
            });

        // 3. Lead Show LOV
        $this->get("/leads/{$lead->id}")
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds) {
                $ids = collect($page->toArray()['props']['units'])->pluck('id')->sort()->values()->all();
                $this->assertSame($expectedSelectableIds, $ids);
            });

        // 4. Booking Create LOV
        $this->get('/bookings/create')
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds) {
                $ids = collect($page->toArray()['props']['availableUnits'])->pluck('id')->sort()->values()->all();
                $this->assertSame($expectedSelectableIds, $ids);
            });

        // 5. Reservation Create LOV
        $this->get('/reservations/create')
            ->assertStatus(200)
            ->assertInertia(function (AssertableInertia $page) use ($expectedSelectableIds) {
                $ids = collect($page->toArray()['props']['units'])->pluck('id')->sort()->values()->all();
                $this->assertSame($expectedSelectableIds, $ids);
            });

        // 6. Server-side validation rejects creating Negotiations on sold, booked, or active-booking units
        foreach ([$unitSold, $unitBooked, $unitActiveBooking] as $blockedUnit) {
            $this->post('/negotiations', [
                'lead_id' => $lead->id,
                'unit_id' => $blockedUnit->id,
                'client_name' => $lead->name,
                'client_phone' => $lead->phone,
                'offered_price' => 1450000000,
                'payment_scheme' => 'cash_bertahap',
                'installment_months' => 6,
            ])->assertSessionHasErrors(['unit_id']);
        }

        // 7. Server-side validation rejects creating Bookings on sold, booked, or active-booking units
        foreach ([$unitSold, $unitBooked, $unitActiveBooking] as $blockedUnit) {
            $this->post('/bookings', [
                'lead_id' => $lead->id,
                'unit_id' => $blockedUnit->id,
                'booked_by' => $user->id,
                'payment_scheme' => 'cash',
                'booking_date' => '2026-11-15',
                'booking_fee' => 17000000,
                'base_price' => 1500000000,
                'final_price' => 1450000000,
            ])->assertSessionHasErrors(['unit_id']);
        }

        // 8. Converting a negotiation whose unit became sold is blocked cleanly
        $this->post("/negotiations/{$negoOnSold->id}/convert")
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_negotiation_parameter_sync_bidirectional_pdf_and_booking_conversion(): void
    {
        $company = Company::create(['name' => 'PT Kana Nusantara', 'slug' => 'kana-sync-test', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active', 'name' => 'Marketing Kana']);
        $this->actingAs($user);

        // Configure live company parameters (completely distinct from legacy hardcoded strings)
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'company_name'], ['value' => 'PT Kana Nusantara']);
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'standard_booking_fee'], ['value' => '17000000']);
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'spr_default_free_ppn'], ['value' => '1']);
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'spr_default_free_legal'], ['value' => '0']);
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'negotiation_number_format'], ['value' => 'NEGO/KNA/{Y}/{MM}/{NNN}']);
        Setting::updateOrCreate(['company_id' => $company->id, 'key' => 'spr_signatures'], [
            'value' => json_encode([
                'city' => 'Bandung',
                'sig1_name' => 'Budi Santoso',
                'sig1_title' => 'Direktur Operasional Kana',
                'sig2_name' => 'Siti Rahma',
                'sig2_title' => 'Head of Sales Kana',
            ]),
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'name' => 'Kana Residence',
            'code' => 'KNR',
            'location' => 'Jl. Dago Pakar No. 18, Bandung',
            'status' => 'active',
        ]);
        $unitType = UnitType::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Tipe Magnolia',
            'land_area' => 140,
            'building_area' => 210,
            'base_price' => 2200000000,
            'current_price' => 2200000000,
        ]);
        $unit = Unit::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_type_id' => $unitType->id,
            'block' => 'D',
            'number' => '9',
            'status' => 'reserved',
            'final_price' => 2200000000,
        ]);

        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'name' => 'Dewi Lestari',
            'phone' => '081377788899',
            'address' => 'Jl. Riau No. 45, Bandung',
            'identity_number' => '3273010101880001',
            'source' => 'inhouse',
            'status' => 'new',
        ]);

        // Active reservation of Rp 11.000.000 (so UTJ balance = 17jt - 11jt = 6jt)
        $reservation = Reservation::create([
            'company_id' => $company->id,
            'reservation_number' => 'RSV/KNR/2026/001',
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'project_id' => $project->id,
            'created_by' => $user->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => 11000000,
            'payment_method' => 'transfer',
            'expires_at' => now()->addDays(10),
            'status' => 'active',
        ]);

        // 1. Verify settings props exposed to Negotiations Index
        $this->get('/negotiations')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standardBookingFee', 17000000)
                ->where('negotiationSettings.company_name', 'PT Kana Nusantara')
                ->where('negotiationSettings.sig1_name', 'Budi Santoso')
                ->where('negotiationSettings.sig2_name', 'Siti Rahma')
                ->where('negotiationSettings.city', 'Bandung')
            );

        // 2. Create Negotiation with custom form_data -> must sync top-level columns & dynamic defaults
        $this->post('/negotiations', [
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'offered_price' => 2050000000,
            'payment_scheme' => 'cash_bertahap',
            'installment_months' => 6,
            'dp_amount' => 400000000,
            'special_bonus_items' => ['AC Split 2 Unit', 'Canopy Carport'],
            'custom_layout_notes' => 'Buka akses taman belakang',
            'form_data' => [
                'pengajuan' => [
                    'price' => '2.050.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 6X',
                    'dp1_amount' => '400.000.000,-',
                    'booking_fee_total' => '17.000.000,-',
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $negotiation = Negotiation::latest('id')->first();
        $this->assertNotNull($negotiation);
        $this->assertEquals(2050000000, (int) $negotiation->offered_price);
        $this->assertEquals('cash_bertahap', $negotiation->payment_scheme);
        $this->assertEquals(6, (int) $negotiation->installment_months);
        $this->assertEquals(400000000, (int) $negotiation->dp_amount);

        // Check live form_details reflects configured Settings + Reservation, NOT Alonica dummy defaults
        $details = $negotiation->getFormDetails();
        $this->assertStringContainsString('PT Kana Nusantara', $details['kepada']);
        $this->assertEquals('17.000.000,-', $details['pengajuan']['booking_fee_total']);
        $this->assertEquals('11.000.000,-', $details['reservasi']);
        $this->assertEquals('6.000.000,-', $details['pengajuan']['booking_fee']);
        $this->assertEquals('Budi Santoso', $details['jawaban']['sig_mengetahui_name']);
        $this->assertEquals('Siti Rahma', $details['jawaban']['sig_menyetujui_name']);
        $this->assertEquals('Kana Residence', $details['project_name']);
        $this->assertStringContainsString('D', $details['kavling']);
        $this->assertStringContainsString('9', $details['kavling']);

        // 3. Edit Negotiation from Modal (PUT /negotiations/{id}) with form_data change -> syncs DB columns too
        $this->put("/negotiations/{$negotiation->id}", [
            'unit_id' => $unit->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'payment_scheme' => 'cash_bertahap',
            'form_data' => [
                'bonus' => '(AC Split 2 Unit, Canopy Carport, Kitchen Set)',
                'pengajuan' => [
                    'price' => '2.080.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 12X',
                    'dp1_amount' => '450.000.000,-',
                    'dp1_date' => '20 November 2026',
                    'pelunasan_amount' => '1.630.000.000,-',
                    'booking_fee_total' => '17.000.000,-',
                ],
                'jawaban' => [
                    'sig_mengetahui_name' => 'Budi Santoso',
                    'sig_menyetujui_name' => 'Siti Rahma',
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $negotiation->refresh();
        $this->assertEquals(2080000000, (int) $negotiation->offered_price);
        $this->assertEquals('cash_bertahap', $negotiation->payment_scheme);
        $this->assertEquals(12, (int) $negotiation->installment_months);
        $this->assertEquals(450000000, (int) $negotiation->dp_amount);
        $this->assertEquals(['AC Split 2 Unit', 'Canopy Carport', 'Kitchen Set'], $negotiation->special_bonus_items);

        // 3b. Client submits/updates offer via public link (POST /nego/{token})
        $this->post("/nego/{$negotiation->token}", [
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'offered_price' => 2090000000,
            'payment_scheme' => 'cash_bertahap',
            'installment_months' => 10,
            'dp_amount' => 450000000,
            'custom_layout_notes' => 'Buka akses taman belakang',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $negotiation->refresh();
        $this->assertEquals('pending', $negotiation->status);
        $this->assertEquals(10, (int) $negotiation->installment_months);
        $this->assertEquals('2.090.000.000,-', $negotiation->getFormDetails()['pengajuan']['price']);

        // 4. Developer sends Counter Offer -> syncs jawaban.price
        $this->post("/negotiations/{$negotiation->id}/review", [
            'action' => 'counter',
            'counter_price' => 2100000000,
            'counter_notes' => 'Disetujui di 2.1M skema 10 bulan',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $negotiation->refresh();
        $this->assertEquals('counter_offer', $negotiation->status);
        $this->assertEquals(2100000000, (int) $negotiation->counter_price);
        $detailsAfterCounter = $negotiation->getFormDetails();
        $this->assertEquals('2.100.000.000,-', $detailsAfterCounter['jawaban']['price']);

        // 5. Client accepts Counter Offer via public link
        $this->post("/nego/{$negotiation->token}/respond", [
            'response' => 'accepted',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $negotiation->refresh();
        $this->assertEquals('approved', $negotiation->status);

        // 6. Verify HTML & PDF Print sync 1-to-1 with zero dummy leakage
        $htmlResp = $this->get("/negotiations/{$negotiation->id}/pdf?html=1");
        $htmlResp->assertStatus(200);
        $html = $htmlResp->getContent();
        $this->assertStringContainsString('PT Kana Nusantara', $html);
        $this->assertStringContainsString('Kana Residence', $html);
        $this->assertStringContainsString('Dewi Lestari', $html);
        $this->assertStringContainsString('2.090.000.000,-', $html);
        $this->assertStringContainsString('2.100.000.000,-', $html);
        $this->assertStringContainsString('17.000.000,-', $html);
        $this->assertStringContainsString('Budi Santoso', $html);
        $this->assertStringContainsString('Siti Rahma', $html);
        $this->assertStringContainsString('Kitchen Set', $html);
        $this->assertStringContainsString('Buka akses taman belakang', $html);
        $this->assertStringNotContainsString('Alonica', $html);
        $this->assertStringNotContainsString('Maulizar', $html);
        $this->assertStringNotContainsString('Bramantyo', $html);

        $pdfResp = $this->get("/negotiations/{$negotiation->id}/pdf");
        $pdfResp->assertStatus(200);
        $this->assertStringContainsString('%PDF-', $pdfResp->getContent());

        // 7. Convert Negotiation -> Booking/Create receives counter_price, months, reservation_id, standardBookingFee
        $convertResp = $this->post("/negotiations/{$negotiation->id}/convert");
        $convertResp->assertRedirect();
        $redirectUrl = $convertResp->headers->get('Location');
        $this->assertStringContainsString("negotiation_id={$negotiation->id}", $redirectUrl);
        $this->assertStringContainsString("reservation_id={$reservation->id}", $redirectUrl);

        $this->get($redirectUrl)
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standardBookingFee', 17000000)
                ->where('reservedAmount', 11000000)
                ->where('reservation.id', $reservation->id)
                ->where('negotiation.id', $negotiation->id)
                ->where('negotiation.counter_price', 2100000000)
                ->where('negotiation.installment_months', 10)
            );

        // 8. Complete Booking creation and verify links & unit status update
        $bookingResp = $this->post('/bookings', [
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'booked_by' => $user->id,
            'negotiation_id' => $negotiation->id,
            'reservation_id' => $reservation->id,
            'payment_scheme' => 'cash_installment',
            'installment_months' => 10,
            'booking_date' => '2026-11-15',
            'booking_fee' => 17000000,
            'base_price' => 2200000000,
            'final_price' => 2100000000,
            'dp_amount' => 500000000,
            'auto_approve' => true,
        ]);
        $bookingResp->assertRedirect();
        $bookingResp->assertSessionHasNoErrors();
        $this->assertNull(session('error'), (string) session('error'));

        $negotiation->refresh();
        $booking = Booking::find($negotiation->booking_id);
        $this->assertNotNull($booking);
        $this->assertEquals(2100000000, (int) $booking->final_price);
        $this->assertEquals(17000000, (int) $booking->booking_fee);

        $reservation->refresh();
        $this->assertEquals('converted', $reservation->status);
        $this->assertEquals($booking->id, $reservation->booking_id);

        $unit->refresh();
        $this->assertEquals('booked', $unit->status);
    }

    public function test_stale_legacy_17jt_negotiation_syncs_to_11jt_reservation_and_flows_to_spr(): void
    {
        $company = Company::create(['name' => 'PT Sync UTJ Nusantara', 'slug' => 'pt-sync-utj-nusantara', 'status' => 'active']);
        $user = User::factory()->create(['company_id' => $company->id, 'status' => 'active', 'name' => 'Marketing Manager Demo']);
        $this->actingAs($user);

        // Tenant sets standard Booking Fee (UTJ) = Rp 11.000.000,-
        Setting::set('standard_booking_fee', '11000000', $company->id);
        Setting::set('company_name', 'PT Sync UTJ Nusantara', $company->id);

        [$project, , $unit] = $this->createProjectUnit($company, 'SUN', 'B', '12', 'available', 2200000000);
        $project->update(['name' => 'Harmony Residence']);

        $lead = Lead::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'assigned_to' => $user->id,
            'name' => 'Konsumen Sync',
            'phone' => '081211122233',
            'source' => 'inhouse',
            'status' => 'hot',
        ]);

        // 1. Reservation Rp 11.000.000,- created for this lead & unit
        $reservation = Reservation::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'amount' => 11000000,
            'reservation_date' => '2026-11-10',
            'expiry_date' => '2026-11-24',
            'status' => 'active',
        ]);

        // 2. Simulate an existing Negotiation record carrying stale legacy 10jt / 7jt / 17jt form_data
        $negotiation = Negotiation::create([
            'company_id' => $company->id,
            'project_id' => $project->id,
            'unit_id' => $unit->id,
            'lead_id' => $lead->id,
            'created_by' => $user->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'unit_listed_price' => 2200000000,
            'offered_price' => 1950000000,
            'payment_scheme' => 'cash_bertahap',
            'dp_amount' => 300000000,
            'installment_months' => 3,
            'status' => 'pending',
            'form_data' => [
                'pengajuan' => [
                    'price' => '1.950.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 3X',
                    'reservasi' => '10,000,000,-',
                    'reservasi_date' => '08 Sept 2026',
                    'booking_fee' => '7,000,000,-',
                    'booking_fee_total' => '(Total 17,000,000,-)',
                    'dp1_amount' => '300.000.000,-',
                ],
                'jawaban' => [
                    'price' => '1.950.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 3X',
                    'reservasi' => '10,000,000,-',
                    'reservasi_date' => '08 Sept 2026',
                    'booking_fee' => '7,000,000,-',
                    'booking_fee_total' => '(Total 17,000,000,-)',
                    'dp1_amount' => '300.000.000,-',
                    'notes' => [
                        'Booking Fee sebesar Rp17.000.000,- dinyatakan hangus 100% apabila setelah SPR diterbitkan, pembeli membatalkan atau tidak melanjutkan transaksi pembelian unit.',
                        'Ketentuan lain mengacu pada perjanjian.',
                    ],
                ],
            ],
        ]);

        // Verify getFormDetails() auto-reconciles both pengajuan & jawaban to live 11jt reservation & parameter
        $details = $negotiation->getFormDetails();
        $this->assertEquals('11.000.000,-', $details['pengajuan']['reservasi']);
        $this->assertEquals('0,- (Lunas UTJ)', $details['pengajuan']['booking_fee']);
        $this->assertEquals('(Total 11.000.000,-)', $details['pengajuan']['booking_fee_total']);
        $this->assertEquals('11.000.000,-', $details['jawaban']['reservasi']);
        $this->assertEquals('0,- (Lunas UTJ)', $details['jawaban']['booking_fee']);
        $this->assertEquals('(Total 11.000.000,-)', $details['jawaban']['booking_fee_total']);
        $this->assertStringContainsString('Rp 11.000.000,-', $details['jawaban']['notes'][0]);
        $this->assertStringNotContainsString('17.000.000', $details['jawaban']['notes'][0]);

        // 3. Index preselect route works cleanly
        $this->get("/negotiations?create=1&lead_id={$lead->id}&unit_id={$unit->id}")
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standardBookingFee', 11000000)
                ->where('preselect.create', true)
                ->where('preselect.lead_id', (string) $lead->id)
                ->where('preselect.unit_id', (string) $unit->id)
            );

        // 4. Developer updates Jawaban Tab 2 (changes scheme to Cash Bertahap 6X & approves) -> syncs scheme + 11jt UTJ
        $this->put("/negotiations/{$negotiation->id}", [
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'client_name' => $lead->name,
            'client_phone' => $lead->phone,
            'status' => 'approved',
            'counter_price' => 1950000000,
            'form_data' => [
                'pengajuan' => [
                    'price' => '1.950.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 3X',
                    'reservasi' => '10,000,000,-',
                    'booking_fee' => '7,000,000,-',
                    'dp1_amount' => '300.000.000,-',
                ],
                'jawaban' => [
                    'price' => '1.950.000.000,-',
                    'cara_bayar' => 'Cash Bertahap 6X',
                    'reservasi' => '10,000,000,-',
                    'booking_fee' => '7,000,000,-',
                    'dp1_amount' => '350.000.000,-',
                    'notes' => [
                        'Booking Fee sebesar Rp17.000.000,- dinyatakan hangus 100% apabila pembeli membatalkan.',
                    ],
                ],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $negotiation->refresh();
        $this->assertEquals('approved', $negotiation->status);
        $this->assertEquals('cash_bertahap', $negotiation->payment_scheme);
        $this->assertEquals(6, (int) $negotiation->installment_months);
        $this->assertEquals(350000000, (int) $negotiation->dp_amount);

        // Negotiation HTML print shows 11jt UTJ and zero 17jt/10jt/7jt leakage
        $negoHtml = $this->get("/negotiations/{$negotiation->id}/pdf?html=1");
        $negoHtml->assertStatus(200);
        $negoHtml->assertSee('11.000.000,-');
        $negoHtml->assertSee('Lunas UTJ');
        $negoHtml->assertDontSee('17.000.000');
        $negoHtml->assertDontSee('17,000,000');
        $negoHtml->assertDontSee('10,000,000');
        $negoHtml->assertDontSee('7,000,000');

        // 5. Bookings/Create without query params still eager-loads active reservation & approved negotiation on lead
        $this->get('/bookings/create')
            ->assertStatus(200)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('standardBookingFee', 11000000)
                ->where('leads.0.id', $lead->id)
                ->where('leads.0.reservations.0.id', $reservation->id)
                ->where('leads.0.reservations.0.amount', 11000000)
                ->where('leads.0.negotiations.0.id', $negotiation->id)
            );

        // 6. Create Booking & check SPR HTML reflects 11jt UTJ + Reservation credit without hardcoded 15jt / Umala Andara
        $this->post('/bookings', [
            'lead_id' => $lead->id,
            'unit_id' => $unit->id,
            'booked_by' => $user->id,
            'negotiation_id' => $negotiation->id,
            'reservation_id' => $reservation->id,
            'payment_scheme' => 'cash_installment',
            'installment_months' => 6,
            'booking_date' => '2026-11-15',
            'booking_fee' => 11000000,
            'base_price' => 1950000000,
            'final_price' => 1950000000,
            'dp_amount' => 350000000,
            'auto_approve' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $booking = Booking::where('lead_id', $lead->id)->firstOrFail();
        $sprResp = $this->get("/bookings/{$booking->id}/spr?html=1");
        $sprResp->assertStatus(200);
        $sprResp->assertSee('11.000.000');
        $sprResp->assertSee('Kredit Reservasi Rp 11.000.000');
        $sprResp->assertDontSee('15.000.000');
        $sprResp->assertDontSee('Umala Andara');
    }
}
