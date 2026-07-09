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
            ->with(['project']);

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
                    'status' => $lead->status,
                    'last_message' => $lastMsg?->message ?? '',
                    'last_message_time' => $lastMsg?->created_at ? $lastMsg->created_at->diffForHumans() : '',
                    'last_message_timestamp' => $lastMsg?->created_at ? $lastMsg->created_at->timestamp : 0,
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

        return Inertia::render('WhatsApp/Inbox', [
            'chats' => $leads,
            'activeLeads' => $activeLeads,
            'partnerBanks' => $partnerBanks,
        ]);
    }

    /**
     * Fetch conversation history for a specific phone number
     */
    public function getMessages($phone)
    {
        $messages = ChatMessage::where('phone', $phone)
            ->orderBy('created_at', 'asc')
            ->take(100)
            ->get();

        return response()->json($messages);
    }

    /**
     * Send outgoing WhatsApp message via Meta Cloud API and log it
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $phone = $request->phone;
        $messageText = $request->message;

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

        // Find matching lead
        $lead = Lead::where('phone', $phone)->first();

        // Log to database
        $msg = ChatMessage::create([
            'lead_id' => $lead?->id,
            'phone' => $phone,
            'direction' => 'outgoing',
            'message' => $messageText,
            'status' => 'sent',
        ]);

        // Recalculate lead score and update last contacted
        if ($lead) {
            $lead->update(['last_contacted_at' => now()]);
            // Log activity
            \App\Models\LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'whatsapp',
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

    /**
     * Log a manually sent WhatsApp message to keep history
     */
    public function logManualSend(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $phone = $request->phone;
        $messageText = $request->message;

        $lead = Lead::where('phone', $phone)->first();

        // Log to database
        $msg = ChatMessage::create([
            'lead_id' => $lead?->id,
            'phone' => $phone,
            'direction' => 'outgoing',
            'message' => $messageText,
            'status' => 'delivered', // mark as delivered since user sent it manually
        ]);

        if ($lead) {
            $lead->update(['last_contacted_at' => now()]);
            \App\Models\LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'whatsapp',
                'description' => '[Pesan Manual WA] ' . $messageText,
                'completed_at' => now(),
            ]);
            $lead->recalculateScore();
        }

        return response()->json([
            'status' => 'success',
            'message' => $msg
        ]);
    }
}
