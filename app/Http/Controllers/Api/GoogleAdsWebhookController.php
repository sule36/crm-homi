<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleAdsWebhookController extends Controller
{
    /**
     * Handle incoming lead data from Google Ads Webhook.
     *
     * Google Ads can send lead form data via webhook when a user submits
     * a lead form extension. This endpoint receives that data, validates
     * the webhook key, creates a lead, and auto-assigns it to an agent.
     */
    public function handle(Request $request)
    {
        // Verify webhook key
        $storedKey = Setting::where('key', 'google_ads_webhook_key')->value('value');
        $providedKey = $request->header('X-Webhook-Key') ?? $request->query('key');

        if (!$storedKey || $providedKey !== $storedKey) {
            Log::warning('Google Ads Webhook: Invalid or missing webhook key.', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Parse the incoming lead data
        // Google Ads Lead Form Extensions typically send:
        // - lead_id, campaign_id, form_id, user_column_data (name, phone, email, etc.)
        $body = $request->all();

        $name = $body['name']
            ?? $this->extractFromUserColumns($body, 'FULL_NAME')
            ?? 'Google Ads Lead';

        $phone = $body['phone']
            ?? $this->extractFromUserColumns($body, 'PHONE_NUMBER')
            ?? '';

        $email = $body['email']
            ?? $this->extractFromUserColumns($body, 'EMAIL')
            ?? null;

        if (empty($phone) && empty($email)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Phone or email is required.',
            ], 422);
        }

        // Check duplicate by phone
        $existing = Lead::where('phone', $phone)->first();
        if ($existing) {
            // Re-activate stale/lost leads
            if (in_array($existing->status, ['lost', 'stale'])) {
                $existing->update(['status' => 'new', 'last_contacted_at' => now()]);
            }

            \App\Models\LeadActivity::create([
                'lead_id' => $existing->id,
                'user_id' => $existing->assigned_to,
                'type' => 'note',
                'description' => '[RE-AKTIVASI] Lead menghubungi kembali via Google Ads Lead Form.',
                'completed_at' => now(),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Existing lead re-activated.',
                'lead_id' => $existing->id,
            ], 200);
        }

        // Create new lead
        $lead = Lead::create([
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'source' => 'google',
            'utm_campaign' => $body['campaign_id'] ?? $body['utm_campaign'] ?? null,
            'notes' => '[Via Google Ads Lead Form] Campaign: ' . ($body['campaign_name'] ?? $body['campaign_id'] ?? 'Unknown'),
            'status' => 'new',
        ]);

        $lead->recalculateScore();
        LeadAssignmentService::assign($lead);

        Log::info('Google Ads Webhook: Lead created.', ['lead_id' => $lead->id, 'name' => $name]);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead created and assigned.',
            'lead_id' => $lead->id,
        ], 201);
    }

    /**
     * Extract a field from Google Ads user_column_data array format.
     */
    private function extractFromUserColumns(array $body, string $columnId): ?string
    {
        $columns = $body['user_column_data'] ?? [];
        foreach ($columns as $column) {
            if (($column['column_id'] ?? '') === $columnId) {
                return $column['string_value'] ?? null;
            }
        }
        return null;
    }
}
