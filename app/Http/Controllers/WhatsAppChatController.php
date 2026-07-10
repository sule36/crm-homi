<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Lead;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class WhatsAppChatController extends Controller
{
    /**
     * Render the Shared WhatsApp Chat Inbox
     */
    public function index()
    {
        $user = auth()->user();

        // Load leads that have chat messages history.
        // If the user is a sales agent, they can only see their assigned leads' chats.
        $leadsQuery = Lead::whereHas('chatMessages')
            ->with(['chatMessages' => fn($q) => $q->latest()])
            ->with(['project', 'assignedTo']);

        if ($user->hasRole('sales_agent')) {
            $leadsQuery->where('assigned_to', $user->id);
        }

        $leads = $leadsQuery->get()
            ->map(function ($lead) {
                $lastMsg = $lead->chatMessages->first();
                return [
                    'id' => $lead->id,
                    'name' => $lead->name,
                    'phone' => $lead->phone,
                    'project' => $lead->project?->name ?? 'Umum',
                    'project_id' => $lead->project_id,
                    'agent_name' => $lead->assignedTo?->name ?? 'Belum Ditugaskan',
                    'status' => $lead->status,
                    'tags' => $lead->preferences['tags'] ?? [],
                    'last_message' => $lastMsg?->message ?? '',
                    'last_message_time' => $lastMsg?->created_at ? $lastMsg->created_at->diffForHumans() : '',
                    'last_message_timestamp' => $lastMsg?->created_at ? $lastMsg->created_at->timestamp : 0,
                    'platform' => $lastMsg?->platform ?? $lead->source ?? 'whatsapp',
                ];
            })
            ->sortByDesc('last_message_timestamp')
            ->values();

        // Also fetch active leads list for creating a new chat from the inbox
        $allLeadsQuery = Lead::select('id', 'name', 'phone')
            ->whereNotIn('status', ['lost']);

        if ($user->hasRole('sales_agent')) {
            $allLeadsQuery->where('assigned_to', $user->id);
        }
        
        $activeLeads = $allLeadsQuery->get();

        // Pass partner banks so we can calculate live simulations on the fly in the chat!
        $partnerBanks = \App\Models\PartnerBank::where('is_active', true)->get();

        // Ambil stok unit tersedia dikelompokkan berdasarkan project_id
        $availableUnits = \App\Models\Unit::where('status', 'available')
            ->with(['unitType'])
            ->get()
            ->groupBy('project_id')
            ->map(function ($units) {
                return $units->map(function ($unit) {
                    return [
                        'block' => $unit->block,
                        'number' => $unit->number,
                        'type' => $unit->unitType?->name ?? 'Tipe Standar',
                        'price' => $unit->display_price,
                    ];
                });
            });

        // Ambil data proyek untuk lampiran brosur & master plan
        $projects = \App\Models\Project::select('id', 'name', 'brochure_file', 'master_plan_image')
            ->get()
            ->map(function($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'brochure_url' => $p->brochure_file ? asset('storage/' . $p->brochure_file) : null,
                    'master_plan_url' => $p->master_plan_image ? asset('storage/' . $p->master_plan_image) : null,
                ];
            });

        return Inertia::render('WhatsApp/Inbox', [
            'chats' => $leads,
            'activeLeads' => $activeLeads,
            'partnerBanks' => $partnerBanks,
            'availableUnits' => $availableUnits,
            'projects' => $projects,
        ]);
    }

    /**
     * Fetch conversation history and reminders for a specific phone number
     */
    public function getMessages($phone)
    {
        $messages = ChatMessage::where('phone', $phone)
            ->orderBy('created_at', 'asc')
            ->take(100)
            ->get();

        $lead = Lead::where('phone', $phone)->first();
        $reminders = [];
        if ($lead) {
            $reminders = \App\Models\FollowUpReminder::where('lead_id', $lead->id)
                ->orderBy('remind_at', 'asc')
                ->get()
                ->map(function($r) {
                    return [
                        'id' => $r->id,
                        'remind_at_formatted' => $r->remind_at->format('d M Y H:i'),
                        'message' => $r->message,
                        'status' => $r->status
                    ];
                });
        }

        return response()->json([
            'messages' => $messages,
            'reminders' => $reminders
        ]);
    }

    /**
     * Send outgoing WhatsApp message via Meta Cloud API and log it
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $phone = $request->phone;
        $messageText = $request->message;
        $platform = $request->platform;

        // Find matching lead
        $lead = Lead::where('phone', $phone)->first();
        if (!$platform && $lead) {
            $platform = $lead->source;
        }
        $platform = in_array($platform, ['facebook', 'instagram']) ? $platform : 'whatsapp';

        if (in_array($platform, ['facebook', 'instagram'])) {
            $accessToken = Setting::where('key', 'meta_page_access_token')->value('value');
            if (!$accessToken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meta Page Access Token is not configured in Settings.'
                ], 422);
            }

            $url = "https://graph.facebook.com/v19.0/me/messages";
            
            $response = Http::withToken($accessToken)->post($url, [
                'recipient' => [
                    'id' => $phone
                ],
                'messaging_type' => 'RESPONSE',
                'message' => [
                    'text' => $messageText
                ]
            ]);

            if ($response->failed()) {
                Log::error("Failed to send Meta outgoing message to {$platform}: " . $response->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meta API Error: ' . ($response->json('error.message') ?? 'Unknown error occurred.')
                ], 500);
            }
        } else {
            $accessToken = Setting::where('key', 'wa_access_token')->value('value');
            $phoneNumberId = Setting::where('key', 'wa_phone_number_id')->value('value');

            if (!$accessToken || !$phoneNumberId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'WhatsApp API credentials are not configured in Settings.'
                ], 422);
            }

            $formattedPhone = $this->formatPhoneNumber($phone);

            // Send via Meta Graph API
            $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";
            
            $response = Http::withToken($accessToken)->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $formattedPhone,
                'type' => 'text',
                'text' => [
                    'body' => $messageText
                ]
            ]);

            if ($response->failed()) {
                Log::error('WhatsApp Chat: Failed to send message: ' . $response->body());
                return response()->json([
                    'status' => 'error',
                    'message' => 'Meta API Error: ' . ($response->json('error.message') ?? 'Unknown error occurred.')
                ], 500);
            }
        }

        // Log to database
        $msg = ChatMessage::create([
            'lead_id' => $lead?->id,
            'phone' => $phone,
            'direction' => 'outgoing',
            'message' => $messageText,
            'status' => 'sent',
            'platform' => $platform,
        ]);

        // Recalculate lead score and update last contacted
        if ($lead) {
            $lead->update(['last_contacted_at' => now()]);
            // Log activity
            \App\Models\LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => in_array($platform, ['call', 'whatsapp', 'email', 'visit', 'meeting', 'note', 'status_change']) ? $platform : 'note',
                'description' => '[Pesan Keluar CRM] ' . $messageText,
                'completed_at' => now(),
            ]);
            $lead->recalculateScore();
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg
        ]);
    }

    /**
     * Format phone number to international standard (62xxx).
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Update Lead Status directly from WhatsApp Inbox
     */
    public function updateLeadStatus(Request $request, Lead $lead)
    {
        $request->validate([
            'status' => 'required|in:new,contacted,visited,negotiation,booking,won,lost'
        ]);

        $lead->update([
            'status' => $request->status,
            'last_contacted_at' => now()
        ]);

        // Recalculate score
        $lead->recalculateScore();

        // Create activity log
        \App\Models\LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'status_change',
            'description' => "Status diubah menjadi " . ucfirst($request->status) . " dari WhatsApp Inbox.",
            'completed_at' => now(),
        ]);

        return response()->json(['message' => 'Status lead berhasil diperbarui.']);
    }

    public function logManualSend(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $phone = $request->phone;
        $messageText = $request->message;

        $lead = Lead::where('phone', $phone)->first();
        $platform = $request->platform ?? ($lead?->source ?? 'whatsapp');
        $platform = in_array($platform, ['facebook', 'instagram']) ? $platform : 'whatsapp';

        // Log to database
        $msg = ChatMessage::create([
            'lead_id' => $lead?->id,
            'phone' => $phone,
            'direction' => 'outgoing',
            'message' => $messageText,
            'status' => 'delivered', // mark as delivered since user sent it manually
            'platform' => $platform,
        ]);

        if ($lead) {
            $lead->update(['last_contacted_at' => now()]);
            \App\Models\LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => in_array($platform, ['call', 'whatsapp', 'email', 'visit', 'meeting', 'note', 'status_change']) ? $platform : 'note',
                'description' => '[Pesan Manual] ' . $messageText,
                'completed_at' => now(),
            ]);
            $lead->recalculateScore();
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg
        ]);
    }

    /**
     * Generate an AI drafted WhatsApp response based on chat history
     */
    public function generateAiDraft(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'platform' => 'nullable|string',
        ]);

        $phone = $request->phone;
        $lead = Lead::where('phone', $phone)->with(['project', 'assignedTo'])->first();

        if (!$lead) {
            return response()->json([
                'status' => 'error',
                'message' => 'Prospek dengan nomor telepon tersebut tidak ditemukan.'
            ], 404);
        }

        $platform = $request->platform ?? ($lead->source ?? 'whatsapp');
        $platform = in_array($platform, ['facebook', 'instagram']) ? $platform : 'whatsapp';

        // Fetch last 15 messages for history context
        $messages = ChatMessage::where('phone', $phone)
            ->where('platform', $platform)
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get()
            ->reverse()
            ->values()
            ->map(function ($msg) {
                return [
                    'direction' => $msg->direction, // 'incoming' or 'outgoing'
                    'message' => $msg->message
                ];
            })
            ->toArray();

        // If history is empty, add a dummy welcome prompt context
        if (empty($messages)) {
            $messages[] = [
                'direction' => 'incoming',
                'message' => 'Halo Homi Developer, saya tertarik dengan proyek propertinya.'
            ];
        }

        // Prepare lead info context
        $leadInfo = [
            'name' => $lead->name,
            'project' => $lead->project?->name ?? 'Umum/Semua Proyek',
            'agent_name' => $lead->assignedTo?->name ?? auth()->user()->name ?? 'Konsultan Marketing',
            'platform' => ucfirst($platform)
        ];

        // Call Gemini Service
        $gemini = new \App\Services\GeminiService();
        $aiDraft = $gemini->draftReply($messages, $leadInfo);

        return response()->json([
            'status' => 'success',
            'draft' => $aiDraft
        ]);
    }

    /**
     * Update Lead Tags/Labels directly from WhatsApp Inbox
     */
    public function updateLeadTags(Request $request, Lead $lead)
    {
        $request->validate([
            'tags' => 'nullable|array'
        ]);

        $preferences = $lead->preferences ?? [];
        $preferences['tags'] = $request->tags ?? [];

        $lead->update([
            'preferences' => $preferences
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Label lead berhasil diperbarui.'
        ]);
    }

    /**
     * Create follow-up reminder from WhatsApp Inbox
     */
    public function createReminder(Request $request)
    {
        $request->validate([
            'lead_id' => 'required|exists:leads,id',
            'remind_at' => 'required|date',
            'message' => 'required|string',
        ]);

        $reminder = \App\Models\FollowUpReminder::create([
            'lead_id' => $request->lead_id,
            'user_id' => auth()->id(),
            'remind_at' => \Carbon\Carbon::parse($request->remind_at),
            'message' => $request->message,
            'status' => 'pending',
        ]);

        return response()->json([
            'status' => 'success',
            'reminder' => [
                'id' => $reminder->id,
                'remind_at_formatted' => $reminder->remind_at->format('d M Y H:i'),
                'message' => $reminder->message,
                'status' => $reminder->status
            ]
        ]);
    }
}
