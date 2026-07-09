<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Models\Project;
use App\Models\Unit;

class GeminiService
{
    protected $apiKey;
    protected $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

    public function __construct()
    {
        $dbKey = \App\Models\Setting::where('key', 'gemini_api_key')->value('value');
        $this->apiKey = !empty($dbKey) ? $dbKey : (config('services.gemini.key') ?? env('GEMINI_API_KEY'));
    }

    /**
     * Generate an AI drafted response based on conversation history and lead/project context
     */
    public function draftReply(array $history, array $leadInfo)
    {
        if (!$this->apiKey) {
            return "Maaf, API Key Gemini belum dikonfigurasi di file .env server Anda. Silakan hubungi admin untuk mengisi GEMINI_API_KEY.";
        }

        $knowledge = $this->getHomiKnowledge();
        
        $customerName = $leadInfo['name'] ?? 'Konsumen';
        $projectName = $leadInfo['project'] ?? 'Proyek Homi';
        $agentName = $leadInfo['agent_name'] ?? 'Konsultan Marketing';

        // Custom Homi Prompt
        $systemPrompt = "Kamu adalah 'Homi AI Copilot', asisten chat sales properti dari Homi Developer. Tugasmu adalah menulis draft balasan WhatsApp yang persuasif, sopan, ramah, dan berorientasi penjualan untuk sales agent kami (bernama {$agentName}) yang sedang mengobrol dengan calon pembeli (bernama {$customerName}) mengenai proyek perumahan '{$projectName}'.

GAYA BAHASA & STRATEGI:
1. Nada bicara ramah, sopan, komunikatif, profesional, dan menggunakan sapaan hangat khas Indonesia (Bapak/Ibu/Kak).
2. Tulis draf langsung berupa pesan yang siap dikirim. Jangan tulis kalimat pembuka asisten seperti 'Ini drafnya: ...' atau tanda kutip di awal dan akhir pesan.
3. Fokus pada penawaran nilai proyek, ajakan site visit (kunjungan lokasi), atau kelengkapan berkas KPR jika mereka menanyakan simulasi KPR.
4. Gunakan data proyek resmi dari Homi Developer yang tertera di bawah. Jangan mengarang informasi/spesifikasi di luar data yang valid.
5. Gunakan format teks WhatsApp seperti tanda bintang (*) untuk teks tebal agar pesan lebih terstruktur dan mudah dibaca.
6. Buat pesan ringkas dan padat agar konsumen nyaman membacanya di layar HP (maksimal 3-4 paragraf kecil).

DATA PROYEK HOMI DEVELOPER:
$knowledge";

        // Map conversation history
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => $msg['direction'] === 'incoming' ? 'user' : 'model',
                'parts' => [['text' => $msg['message']]]
            ];
        }

        // Add context for the AI draft request
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => "Tolong buatkan draf balasan WhatsApp terbaik untuk melanjutkan obrolan di atas secara alami, membimbing mereka ke arah site visit atau kelanjutan transaksi."]]
        ];

        try {
            $url = $this->apiUrl . '?key=' . $this->apiKey;
            
            $payload = [
                'contents' => $contents,
                'systemInstruction' => [
                    'parts' => [['text' => $systemPrompt]]
                ],
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 1024,
                ]
            ];

            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                            ->timeout(30)
                            ->post($url, $payload);

            // Fallback for older API versions or formats if systemInstruction is rejected
            if ($response->status() === 400) {
                $fallbackContents = $contents;
                if (!empty($fallbackContents)) {
                    $lastIdx = count($fallbackContents) - 1;
                    $fallbackContents[$lastIdx]['parts'][0]['text'] = "INSTRUCTIONS:\n$systemPrompt\n\nLAST CHAT CONTEXT:\n" . $fallbackContents[$lastIdx]['parts'][0]['text'];
                }

                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                                ->timeout(30)
                                ->post($url, ['contents' => $fallbackContents]);
            }

            if ($response->failed()) {
                \Illuminate\Support\Facades\Log::error("Gemini API Error [" . $response->status() . "]: " . $response->body());
                return "Halo Bapak/Ibu *{$customerName}*, terima kasih telah menunggu. Mengenai proyek perumahan *{$projectName}*, apakah hari ini atau akhir pekan besok ada waktu luang untuk kami jadwalkan site visit langsung ke lokasi perumahan kami? 😊";
            }

            $result = $response->json();
            return $result['candidates'][0]['content']['parts'][0]['text'] ?? "Draf balasan tidak dapat dibuat otomatis.";

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Gemini Service Exception: " . $e->getMessage());
            return "Halo Bapak/Ibu *{$customerName}*, terima kasih telah menunggu. Apakah ada detail spesifikasi unit atau skema pembayaran di *{$projectName}* yang bisa saya bantu jelaskan lebih lanjut?";
        }
    }

    /**
     * Construct Homi projects knowledge base
     */
    protected function getHomiKnowledge()
    {
        return Cache::remember('homi_ai_knowledge', 1800, function () {
            $projects = Project::select('id', 'name', 'code', 'description', 'location', 'price_range_min', 'price_range_max', 'total_units', 'available_units')->get();
            
            $knowledge = "";
            foreach ($projects as $p) {
                $minPrice = number_format($p->price_range_min);
                $maxPrice = number_format($p->price_range_max);
                $knowledge .= "- PROYEK: {$p->name} ({$p->code}) | Lokasi: {$p->location} | Kisaran Harga: Rp {$minPrice} s.d Rp {$maxPrice} | Deskripsi: {$p->description} | Unit Tersedia: {$p->available_units} dari total {$p->total_units} unit.\n";
                
                // Add unit types info
                $types = \App\Models\UnitType::where('project_id', $p->id)->get();
                if ($types->isNotEmpty()) {
                    $knowledge .= "  Tipe Unit di {$p->name}:\n";
                    foreach ($types as $t) {
                        $price = number_format($t->current_price);
                        $knowledge .= "  * Tipe {$t->name} | Harga: Rp {$price} | KT/KM: {$t->bedroom}/{$t->bathroom} | Dimensi: LB {$t->building_area}m² / LT {$t->land_area}m²\n";
                    }
                }
            }
            return $knowledge;
        });
    }
}
