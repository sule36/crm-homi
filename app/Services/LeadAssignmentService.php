<?php

namespace App\Services;

use App\Models\User;
use App\Models\Lead;

class LeadAssignmentService
{
    /**
     * Assign a lead to a sales agent using Round Robin logic.
     */
    public static function assign(Lead $lead, bool $notify = true)
    {
        $query = User::role('sales_agent')->where('status', 'active')->where('is_accepting_leads', true);
        if ($lead->project_id) {
            $query->where('project_id', $lead->project_id);
        }

        // Get agents with their active lead count
        $agents = $query->withCount(['leads as active_leads' => fn ($q) => $q->whereNotIn('status', ['won', 'lost'])])->get();

        if ($agents->isEmpty()) {
            return null;
        }

        // Filter agents who have capacity and sort by lowest workload ratio
        $targetAgent = $agents->filter(fn ($agent) => $agent->active_leads < ($agent->lead_capacity ?? 50))
            ->sortBy(fn ($agent) => $agent->active_leads / max(1, $agent->lead_capacity ?? 50))
            ->first();

        // Fallback: if everyone is full, just give it to the one with absolute lowest leads
        if (!$targetAgent) {
            $targetAgent = $agents->sortBy('active_leads')->first();
        }

        if ($targetAgent) {
            $lead->update([
                'assigned_to' => $targetAgent->id,
                'status' => 'new'
            ]);

            // Notify agent via WhatsApp if requested
            if ($notify) {
                self::notifyAgent($targetAgent, $lead);
            }
        }

        return $targetAgent;
    }

    /**
     * Send WhatsApp notification to the assigned sales agent.
     */
    public static function notifyAgent(User $agent, Lead $lead)
    {
        if (empty($agent->phone)) {
            return;
        }

        $accessToken = \App\Models\Setting::where('key', 'wa_access_token')->value('value');
        $phoneNumberId = \App\Models\Setting::where('key', 'wa_phone_number_id')->value('value');

        if (!$accessToken || !$phoneNumberId) {
            \Illuminate\Support\Facades\Log::warning('WhatsApp Cloud API tokens are not configured for agent notification.');
            return;
        }

        $agentPhone = self::formatPhoneNumber($agent->phone);
        $customerPhone = self::formatPhoneNumber($lead->phone);

        $sourceLabel = ucfirst(str_replace('_', ' ', $lead->source));
        $projectName = $lead->project?->name ?? 'Semua Proyek';
        
        // WhatsApp template message to Sales Agent
        $message = "🔔 *PROSPEK BARU MASUK (FORM)*\n\n" .
                   "Halo *{$agent->name}*, Anda baru saja ditugaskan untuk mem-follow up prospek baru:\n\n" .
                   "👤 *Nama:* {$lead->name}\n" .
                   "📞 *No. HP:* {$lead->phone}\n" .
                   "🏗️ *Proyek:* {$projectName}\n" .
                   "📢 *Sumber:* {$sourceLabel}\n" .
                   (empty($lead->notes) ? "" : "📝 *Catatan:* {$lead->notes}\n") .
                   "\n" .
                   "⚡ *Segera hubungi prospek di link berikut:* \n" .
                   "👉 https://wa.me/{$customerPhone}?text=" . urlencode("Halo Bpk/Ibu *{$lead->name}*, perkenalkan saya *{$agent->name}* dari Homi Developer. Terima kasih telah menyatakan minat pada proyek *{$projectName}*...");

        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $agentPhone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ]);

        if ($response->failed()) {
            \Illuminate\Support\Facades\Log::error('Failed to send WhatsApp notification to agent: ' . $response->body());
        }
    }

    /**
     * Format phone number to international standard (62xxx).
     */
    private static function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }
        return $phone;
    }
}
