<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles & create a sales agent
        $this->artisan('db:seed');
    }

    public function test_google_ads_webhook_unauthorized_without_key(): void
    {
        Setting::updateOrCreate(['key' => 'google_ads_webhook_key'], ['value' => 'secret_key']);

        $response = $this->postJson('/api/webhooks/google-ads', [
            'phone' => '081234567890',
            'name' => 'Budi Google',
        ]);

        $response->assertStatus(403);
    }

    public function test_google_ads_webhook_creates_and_assigns_lead_with_correct_key(): void
    {
        Setting::updateOrCreate(['key' => 'google_ads_webhook_key'], ['value' => 'secret_key']);

        $response = $this->postJson('/api/webhooks/google-ads', [
            'phone' => '081234567890',
            'name' => 'Budi Google',
            'email' => 'budi.google@gmail.com',
            'campaign_name' => 'Google Search Ads - Properti',
        ], [
            'X-Webhook-Key' => 'secret_key'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('leads', [
            'name' => 'Budi Google',
            'phone' => '081234567890',
            'source' => 'google',
        ]);
        
        // Assert it was assigned to a sales agent
        $lead = Lead::where('phone', '081234567890')->first();
        $this->assertNotNull($lead->assigned_to);
    }

    public function test_meta_lead_ads_webhook_verification(): void
    {
        Setting::updateOrCreate(['key' => 'meta_leadads_verify_token'], ['value' => 'my_verify_token']);

        $response = $this->get('/api/webhooks/meta-leads?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'my_verify_token',
            'hub_challenge' => '123456789'
        ]));

        $response->assertStatus(200);
        $response->assertSee('123456789');
    }

    public function test_meta_lead_ads_webhook_creates_lead(): void
    {
        Setting::updateOrCreate(['key' => 'meta_leadads_access_token'], ['value' => 'meta_graph_token']);

        // Mock Meta Graph API lead details endpoint
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => '12345',
                'platform' => 'instagram',
                'field_data' => [
                    [
                        'name' => 'full_name',
                        'values' => ['Dewi Meta']
                    ],
                    [
                        'name' => 'phone_number',
                        'values' => ['081299998888']
                    ],
                    [
                        'name' => 'email',
                        'values' => ['dewi.meta@gmail.com']
                    ]
                ]
            ], 200)
        ]);

        $response = $this->postJson('/api/webhooks/meta-leads', [
            'object' => 'page',
            'entry' => [
                [
                    'id' => 'page_id_1',
                    'changes' => [
                        [
                            'field' => 'leadgen',
                            'value' => [
                                'leadgen_id' => '12345',
                                'form_id' => 'form_id_1',
                                'page_id' => 'page_id_1',
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('leads', [
            'name' => 'Dewi Meta',
            'phone' => '081299998888',
            'source' => 'instagram',
        ]);
        
        $lead = Lead::where('phone', '081299998888')->first();
        $this->assertNotNull($lead->assigned_to);
    }

    public function test_complete_reminder_marks_completed(): void
    {
        $user = User::create([
            'name' => 'Agent Test',
            'email' => 'agent.test@homi.id',
            'password' => bcrypt('password'),
            'phone' => '0812345678',
            'status' => 'active'
        ]);
        $user->assignRole('sales_agent');

        $lead = Lead::create([
            'name' => 'Lead Test',
            'phone' => '0811112222',
            'status' => 'new',
            'source' => 'website'
        ]);

        $reminder = \App\Models\FollowUpReminder::create([
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'remind_at' => now()->addDay(),
            'message' => 'Test message',
            'status' => 'pending'
        ]);

        $response = $this->actingAs($user)->post("/reminders/{$reminder->id}/complete");
        $response->assertStatus(302); // Redirect back

        $this->assertEquals('completed', $reminder->refresh()->status);
    }
}
