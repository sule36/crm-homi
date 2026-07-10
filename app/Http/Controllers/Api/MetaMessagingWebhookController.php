<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\ChatMessage;
use App\Services\LeadAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaMessagingWebhookController extends Controller
{
    /**
     * Webhook Verification for Meta Messenger / Instagram
     */
    public function verify(Request $request)
    {
        $messengerVerifyToken = Setting::where('key', 'messenger_verify_token')->value('value');
        $instagramVerifyToken = Setting::where('key', 'instagram_verify_token')->value('value');
        
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode && $token) {
            if ($mode === 'subscribe' && ($token === $messengerVerifyToken || $token === $instagramVerifyToken)) {
                return response($challenge, 200);
            }
            return response('Forbidden', 403);
        }
        
        return response('Bad Request', 400);
    }

    /**
     * Handle incoming Meta Messaging events
     */
    public function handle(Request $request)
    {
        $body = $request->all();

        if (isset($body['object']) && in_array($body['object'], ['page', 'instagram'])) {
            $platform = $body['object'] === 'instagram' ? 'instagram' : 'facebook';

            foreach ($body['entry'] as $entry) {
                $messaging = $entry['messaging'] ?? [];
                
                foreach ($messaging as $messagingEvent) {
                    // Only process message events (exclude echos, reads, deliveries)
                    if (isset($messagingEvent['message']) && !empty($messagingEvent['message'])) {
                        $message = $messagingEvent['message'];
                        
                        if (isset($message['is_echo']) && $message['is_echo']) {
                            continue;
                        }

                        $senderId = $messagingEvent['sender']['id'] ?? null;
                        $messageText = $message['text'] ?? 'Attachment/Media Content';

                        if ($senderId) {
                            $this->processIncomingMessage($platform, $senderId, $messageText, $messagingEvent);
                        }
                    }
                }
            }

            return response('EVENT_RECEIVED', 200);
        }

        return response('Not Found', 404);
    }

    /**
     * Process incoming message, match lead, trigger autopilot or log activity
     */
    private function processIncomingMessage(string $platform, string $senderId, string $messageText, array $messagingEvent)
    {
        $existingLead = Lead::where('phone', $senderId)->first();
        $lead = null;

        if (!$existingLead) {
            $name = $this->getProfileName($platform, $senderId);
            
            // Create new lead
            $lead = Lead::create([
                'name' => $name,
                'phone' => $senderId, // Store Page-Scoped User ID in phone column
                'source' => $platform,
                'notes' => "[Via Meta " . ucfirst($platform) . " Chat] Pesan pertama: " . $messageText,
                'status' => 'new',
            ]);

            // Assign via Smart Auto-Assign
            $assignedAgent = LeadAssignmentService::assign($lead, true);
        } else {
            $lead = $existingLead;
            
            // Re-activation logic
            $oldStatus = $existingLead->status;
            if (in_array($oldStatus, ['lost', 'stale'])) {
                $existingLead->update(['status' => 'new', 'last_contacted_at' => now()]);
            } else {
                $existingLead->update(['last_contacted_at' => now()]);
            }

            // Create activity log
            \App\Models\LeadActivity::create([
                'lead_id' => $existingLead->id,
                'user_id' => $existingLead->assigned_to,
                'type' => in_array($platform, ['call', 'whatsapp', 'email', 'visit', 'meeting', 'note', 'status_change']) ? $platform : 'note',
                'description' => '[RE-AKTIVASI] Pelanggan lama menghubungi kembali via ' . ucfirst($platform) . '. Pesan: ' . $messageText,
                'completed_at' => now(),
            ]);

            // Create a FollowUp Reminder for the agent
            if ($existingLead->assigned_to) {
                \App\Models\FollowUpReminder::create([
                    'lead_id' => $existingLead->id,
                    'user_id' => $existingLead->assigned_to,
                    'remind_at' => now()->addMinutes(5),
                    'message' => 'URGENT: Pelanggan lama menghubungi kembali via ' . ucfirst($platform) . '! Segera respon pesannya.',
                ]);

                // Notify agent
                $agent = \App\Models\User::find($existingLead->assigned_to);
                if ($agent) {
                    $existingLead->notes = 'MENGHUBUNGI KEMBALI: ' . $messageText;
                    LeadAssignmentService::notifyAgent($agent, $existingLead);
                }
            }
        }

        // Save incoming message to the chat history
        if ($lead) {
            ChatMessage::create([
                'lead_id' => $lead->id,
                'phone' => $senderId,
                'direction' => 'incoming',
                'message' => $messageText,
                'status' => 'received',
                'platform' => $platform,
            ]);

            // Check if Autopilot AI is enabled
            $autopilotEnabled = Setting::where('key', 'ai_autopilot_' . $platform)->value('value');
            if ($autopilotEnabled === '1' || $autopilotEnabled === 'true' || $autopilotEnabled === true) {
                $this->sendAutopilotResponse($lead, $senderId, $platform);
            }
        }
    }

    /**
     * Generate Gemini AI response and send automatically
     */
    private function sendAutopilotResponse(Lead $lead, string $senderId, string $platform)
    {
        // Fetch last 15 messages for history context
        $messages = ChatMessage::where('phone', $senderId)
            ->where('platform', $platform)
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($msg) {
                return [
                    'direction' => $msg->direction,
                    'message' => $msg->message
                ];
            })
            ->toArray();

        $leadInfo = [
            'name' => $lead->name,
            'project' => $lead->project?->name ?? 'Umum/Semua Proyek',
            'agent_name' => $lead->assignedTo?->name ?? 'Konsultan Marketing'
        ];

        // Call Gemini Service to get a generated reply
        $gemini = new \App\Services\GeminiService();
        $aiReply = $gemini->draftReply($messages, $leadInfo);

        // Send message via Meta Graph API
        $success = $this->sendMetaOutgoingMessage($platform, $senderId, $aiReply);

        if ($success) {
            // Log to database
            ChatMessage::create([
                'lead_id' => $lead->id,
                'phone' => $senderId,
                'direction' => 'outgoing',
                'message' => $aiReply,
                'status' => 'sent',
                'platform' => $platform,
            ]);

            // Update lead contacted time
            $lead->update(['last_contacted_at' => now()]);

            // Log activity
            \App\Models\LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $lead->assigned_to,
                'type' => in_array($platform, ['call', 'whatsapp', 'email', 'visit', 'meeting', 'note', 'status_change']) ? $platform : 'note',
                'description' => '[Autopilot AI] ' . $aiReply,
                'completed_at' => now(),
            ]);
            $lead->recalculateScore();
        }
    }

    /**
     * Send outgoing message to Meta Messenger / Instagram
     */
    private function sendMetaOutgoingMessage(string $platform, string $recipientId, string $messageText)
    {
        $accessToken = Setting::where('key', 'meta_page_access_token')->value('value');
        if (!$accessToken) {
            Log::error("Meta Page Access Token is not configured for Autopilot.");
            return false;
        }

        $url = "https://graph.facebook.com/v19.0/me/messages";
        
        $response = Http::withToken($accessToken)->post($url, [
            'recipient' => [
                'id' => $recipientId
            ],
            'messaging_type' => 'RESPONSE',
            'message' => [
                'text' => $messageText
            ]
        ]);

        if ($response->failed()) {
            Log::error("Failed to send Meta outgoing message to {$platform}: " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Fetch user profile name from Meta API
     */
    private function getProfileName(string $platform, string $scopedId): string
    {
        $accessToken = Setting::where('key', 'meta_page_access_token')->value('value');
        if (!$accessToken) {
            return $platform === 'instagram' ? 'Prospek Instagram' : 'Prospek Facebook';
        }

        try {
            if ($platform === 'instagram') {
                $response = Http::timeout(5)->get("https://graph.facebook.com/v19.0/{$scopedId}", [
                    'fields' => 'username,name',
                    'access_token' => $accessToken
                ]);
                if ($response->successful()) {
                    return $response->json('name') ?? $response->json('username') ?? 'Prospek Instagram';
                }
            } else {
                $response = Http::timeout(5)->get("https://graph.facebook.com/v19.0/{$scopedId}", [
                    'fields' => 'first_name,last_name',
                    'access_token' => $accessToken
                ]);
                if ($response->successful()) {
                    $firstName = $response->json('first_name') ?? '';
                    $lastName = $response->json('last_name') ?? '';
                    $fullName = trim($firstName . ' ' . $lastName);
                    return !empty($fullName) ? $fullName : 'Prospek Facebook';
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch Meta profile: " . $e->getMessage());
        }

        return $platform === 'instagram' ? 'Prospek Instagram' : 'Prospek Facebook';
    }
}
