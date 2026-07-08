<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use App\Models\Setting;
use App\Services\LeadAssignmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Webhook Verification for Meta Cloud API
     */
    public function verify(Request $request)
    {
        $verifyToken = Setting::where('key', 'wa_verify_token')->value('value');
        
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
     * Handle incoming WhatsApp messages
     */
    public function handle(Request $request)
    {
        // 1. Verify it's a WhatsApp message event
        $body = $request->all();

        if (isset($body['object']) && $body['object'] === 'whatsapp_business_account') {
            
            // Loop through entries
            foreach ($body['entry'] as $entry) {
                $changes = $entry['changes'][0]['value'] ?? [];
                
                // Only process if it contains messages (ignore status updates like read/delivered)
                if (isset($changes['messages']) && !empty($changes['messages'])) {
                    $message = $changes['messages'][0];
                    $contact = $changes['contacts'][0] ?? null;

                    $phoneNumber = $message['from'];
                    $messageText = $message['text']['body'] ?? 'Media/Other Content';
                    $contactName = $contact['profile']['name'] ?? 'Pelanggan WA';

                    // 2. Process Lead
                    $this->processWhatsAppLead($phoneNumber, $contactName, $messageText, $message);
                }
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Not Found', 404);
    }

    private function processWhatsAppLead(string $phone, string $name, string $messageText, array $messageData = [])
    {
        // Check if lead already exists (robust matching for 08xxx vs 62xxx formats)
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        $normalizedPhone = str_starts_with($cleanPhone, '0') ? '62' . substr($cleanPhone, 1) : $cleanPhone;
        $localPhone = str_starts_with($normalizedPhone, '62') ? '0' . substr($normalizedPhone, 2) : $normalizedPhone;

        $existingLead = Lead::whereIn('phone', [$phone, $normalizedPhone, $localPhone])->first();
        $lead = null;

        if (!$existingLead) {
            // Smart Source Detection
            $source = 'other';
            $messageTextLower = strtolower($messageText);
            
            // First try checking Meta's official referral object (if they clicked from an ad)
            if (isset($messageData['referral']['source_url'])) {
                $sourceUrl = strtolower($messageData['referral']['source_url']);
                if (str_contains($sourceUrl, 'ig.me') || str_contains($sourceUrl, 'instagram')) {
                    $source = 'instagram';
                } elseif (str_contains($sourceUrl, 'fb.me') || str_contains($sourceUrl, 'facebook')) {
                    $source = 'facebook';
                }
            }
            
            // Fallback to checking the message text
            if ($source === 'other') {
                if (str_contains($messageTextLower, 'instagram') || str_contains($messageTextLower, 'ig')) {
                    $source = 'instagram';
                } elseif (str_contains($messageTextLower, 'facebook') || str_contains($messageTextLower, 'fb')) {
                    $source = 'facebook';
                }
            }

            // Create new lead
            $lead = Lead::create([
                'name' => $name,
                'phone' => $phone,
                'source' => $source,
                'notes' => '[Via WhatsApp API] Pesan pertama: ' . $messageText,
                'status' => 'new',
            ]);

            // Assign via Smart Auto-Assign (pass false to avoid duplicate WA notification to agent since it's a direct message chat)
            $assignedAgent = LeadAssignmentService::assign($lead, false);

            // Send Auto-Reply if configured
            if ($assignedAgent) {
                $this->sendAutoReply($phone, $assignedAgent->name);
            }
        } else {
            // LEAD RE-ACTIVATION LOGIC
            $lead = $existingLead;
            
            // 1. Update status to 'new' if they were lost or stale, and update last_contacted
            $oldStatus = $existingLead->status;
            if (in_array($oldStatus, ['lost', 'stale'])) {
                $existingLead->update(['status' => 'new', 'last_contacted_at' => now()]);
            } else {
                $existingLead->update(['last_contacted_at' => now()]);
            }

            // 2. Create an activity log
            \App\Models\LeadActivity::create([
                'lead_id' => $existingLead->id,
                'user_id' => $existingLead->assigned_to, // Assigned agent
                'type' => 'whatsapp',
                'description' => '[RE-AKTIVASI] Pelanggan lama menghubungi kembali via Ads WhatsApp. Pesan: ' . $messageText,
                'completed_at' => now(),
            ]);

            // 3. Create a FollowUp Reminder for the agent (if assigned)
            if ($existingLead->assigned_to) {
                \App\Models\FollowUpReminder::create([
                    'lead_id' => $existingLead->id,
                    'user_id' => $existingLead->assigned_to,
                    'remind_at' => now()->addMinutes(5), // Remind in 5 minutes
                    'message' => 'URGENT: Pelanggan lama menghubungi kembali via WhatsApp Iklan! Segera respon pesannya.',
                ]);

                // Auto-reply for existing lead (optional, but good for UX)
                $agent = \App\Models\User::find($existingLead->assigned_to);
                if ($agent) {
                    $this->sendAutoReply($phone, $agent->name);
                }
            }
        }

        // Save incoming message to the chat history
        if ($lead) {
            \App\Models\ChatMessage::create([
                'lead_id' => $lead->id,
                'phone' => $phone,
                'direction' => 'incoming',
                'message' => $messageText,
                'status' => 'received',
            ]);
        }
    }

    private function sendAutoReply(string $toPhone, string $agentName)
    {
        $accessToken = Setting::where('key', 'wa_access_token')->value('value');
        $phoneNumberId = Setting::where('key', 'wa_phone_number_id')->value('value');
        $autoReplyMsg = Setting::where('key', 'wa_auto_reply_message')->value('value');

        if (!$accessToken || !$phoneNumberId) {
            Log::warning('WhatsApp Cloud API tokens are not configured.');
            return;
        }

        // Default message if not set
        if (!$autoReplyMsg) {
            $autoReplyMsg = "Halo! Terima kasih telah menghubungi Homi. Tim konsultan kami, {agent_name}, akan segera membalas pesan Anda.";
        }

        // Replace placeholders
        $finalMessage = str_replace('{agent_name}', $agentName, $autoReplyMsg);

        // Send via Meta API
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";
        
        $response = Http::withToken($accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'text',
            'text' => [
                'body' => $finalMessage
            ]
        ]);

        if ($response->failed()) {
            Log::error('Failed to send WhatsApp auto-reply: ' . $response->body());
        }
    }
}
