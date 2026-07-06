<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaLeadAdsWebhookController extends Controller
{
    /**
     * Webhook Verification for Meta Lead Ads (Facebook/Instagram Lead Forms).
     * This is separate from the WhatsApp webhook.
     */
    public function verify(Request $request)
    {
        $verifyToken = Setting::where('key', 'meta_leadads_verify_token')->value('value');

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200);
            }
            return response('Forbidden', 403);
        }

        return response('Bad Request', 400);
    }

    /**
     * Handle incoming lead data from Facebook/Instagram Lead Ads.
     *
     * When a user fills out a Lead Ad form on Facebook or Instagram,
     * Meta sends a webhook notification with the leadgen_id.
     * We then fetch the actual lead data from the Graph API.
     */
    public function handle(Request $request)
    {
        $body = $request->all();

        if (!isset($body['object']) || $body['object'] !== 'page') {
            return response('Not Found', 404);
        }

        foreach ($body['entry'] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                if (($change['field'] ?? '') === 'leadgen') {
                    $leadgenId = $change['value']['leadgen_id'] ?? null;
                    $formId = $change['value']['form_id'] ?? null;
                    $pageId = $change['value']['page_id'] ?? null;

                    if ($leadgenId) {
                        $this->processMetaLead($leadgenId, $formId, $pageId);
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    /**
     * Fetch lead data from Meta Graph API and create a CRM lead.
     */
    private function processMetaLead(string $leadgenId, ?string $formId, ?string $pageId)
    {
        $accessToken = Setting::where('key', 'meta_leadads_access_token')->value('value');

        if (!$accessToken) {
            Log::warning('Meta Lead Ads: Access token not configured.');
            return;
        }

        // Fetch the actual lead data from Meta Graph API
        $response = Http::get("https://graph.facebook.com/v19.0/{$leadgenId}", [
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            Log::error('Meta Lead Ads: Failed to fetch lead data.', [
                'leadgen_id' => $leadgenId,
                'response' => $response->body(),
            ]);
            return;
        }

        $leadData = $response->json();
        $fieldData = collect($leadData['field_data'] ?? []);

        $name = $fieldData->firstWhere('name', 'full_name')['values'][0]
            ?? $fieldData->firstWhere('name', 'first_name')['values'][0]
            ?? 'Meta Lead';

        $phone = $fieldData->firstWhere('name', 'phone_number')['values'][0]
            ?? '';

        $email = $fieldData->firstWhere('name', 'email')['values'][0]
            ?? null;

        if (empty($phone) && empty($email)) {
            Log::warning('Meta Lead Ads: No phone or email in lead data.', ['leadgen_id' => $leadgenId]);
            return;
        }

        // Determine source from platform
        $source = 'facebook'; // Default
        $platform = $leadData['platform'] ?? '';
        if (str_contains(strtolower($platform), 'instagram') || str_contains(strtolower($platform), 'ig')) {
            $source = 'instagram';
        }

        // Check duplicate
        if (!empty($phone)) {
            $existing = Lead::where('phone', $phone)->first();
            if ($existing) {
                if (in_array($existing->status, ['lost', 'stale'])) {
                    $existing->update(['status' => 'new', 'last_contacted_at' => now()]);
                }
                \App\Models\LeadActivity::create([
                    'lead_id' => $existing->id,
                    'user_id' => $existing->assigned_to,
                    'type' => 'note',
                    'description' => "[RE-AKTIVASI] Lead masuk kembali via Meta Lead Ads Form ({$source}).",
                    'completed_at' => now(),
                ]);
                return;
            }
        }

        // Create new lead
        $lead = Lead::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'source' => $source,
            'notes' => "[Via Meta Lead Ads Form] Form ID: {$formId}, Page ID: {$pageId}",
            'status' => 'new',
        ]);

        $lead->recalculateScore();
        LeadAssignmentService::assign($lead);

        Log::info('Meta Lead Ads: Lead created.', ['lead_id' => $lead->id, 'name' => $name, 'source' => $source]);
    }
}
