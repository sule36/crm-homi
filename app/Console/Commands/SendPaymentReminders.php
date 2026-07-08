<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PaymentSchedule;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendPaymentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-payment-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send automatic WhatsApp payment reminders to customers 3 days before, on the day of, and 7 days after due date.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accessToken = Setting::where('key', 'wa_access_token')->value('value');
        $phoneNumberId = Setting::where('key', 'wa_phone_number_id')->value('value');

        if (!$accessToken || !$phoneNumberId) {
            $this->warn('WhatsApp API credentials are not configured in settings. Skipping automatic reminders.');
            return;
        }

        $datesToCheck = [
            'h_3' => now()->addDays(3)->toDateString(),
            'h_0' => now()->toDateString(),
            'h_plus_7' => now()->subDays(7)->toDateString(),
        ];

        $count = 0;

        foreach ($datesToCheck as $type => $date) {
            $schedules = PaymentSchedule::with(['booking.lead', 'booking.unit.project'])
                ->where('status', 'upcoming')
                ->whereDate('due_date', $date)
                ->get();

            foreach ($schedules as $schedule) {
                $booking = $schedule->booking;
                if (!$booking || !$booking->lead || empty($booking->lead->phone)) {
                    continue;
                }

                $customerPhone = $this->formatPhoneNumber($booking->lead->phone);
                $customerName = $booking->lead->name;
                $unitCode = ($booking->unit?->block ?? '') . ($booking->unit?->number ?? '');
                $projectName = $booking->unit?->project?->name ?? 'Proyek Perumahan';
                $amountFormatted = 'Rp ' . number_format($schedule->amount, 0, ',', '.');
                $dueDateFormatted = $schedule->due_date->toLocaleDateString('id-ID', ['dateStyle' => 'long']);
                $trackingLink = url("/track/{$booking->tracking_token}");

                $message = '';

                if ($type === 'h_3') {
                    $message = "🔔 *PENGINGAT JATUH TEMPO (H-3)*\n\nHalo Bapak/Ibu *{$customerName}*,\n\nMengingatkan kembali bahwa cicilan pembayaran Anda untuk unit *{$unitCode}* ({$projectName}) sebesar *{$amountFormatted}* ({$schedule->label}) akan jatuh tempo pada *{$dueDateFormatted}*.\n\nAnda dapat memantau riwayat angsuran dan melakukan konfirmasi pembayaran di tautan berikut:\n🔗 {$trackingLink}\n\nTerima kasih atas kerja samanya.\n*Homi Developer*";
                } elseif ($type === 'h_0') {
                    $message = "📢 *BATAS WAKTU PEMBAYARAN (HARI INI)*\n\nHalo Bapak/Ibu *{$customerName}*,\n\nHari ini adalah batas waktu pembayaran cicilan Anda untuk unit *{$unitCode}* ({$projectName}) sebesar *{$amountFormatted}* ({$schedule->label}).\n\nMohon lakukan transfer dan unggah bukti bayar Anda di tautan berikut:\n🔗 {$trackingLink}\n\nTerima kasih atas komitmennya.\n*Homi Developer*";
                } elseif ($type === 'h_plus_7') {
                    $message = "⚠️ *PERINGATAN KETERLAMBATAN (TELAT 7 HARI)*\n\nHalo Bapak/Ibu *{$customerName}*,\n\nKami mengonfirmasi bahwa cicilan pembayaran sebesar *{$amountFormatted}* ({$schedule->label}) untuk unit *{$unitCode}* ({$projectName}) telah melewati batas jatuh tempo sejak *{$dueDateFormatted}* (melewati 7 hari).\n\nMohon segera selesaikan pembayaran dan konfirmasikan di tautan berikut untuk menghindari denda administratif:\n🔗 {$trackingLink}\n\nTerima kasih.\n*Homi Developer*";
                }

                if (!empty($message)) {
                    $this->sendWhatsApp($phoneNumberId, $accessToken, $customerPhone, $message);
                    $count++;
                }
            }
        }

        $this->info("Successfully sent {$count} payment reminders.");
        Log::info("WhatsApp payment reminders command: sent {$count} messages.");
    }

    /**
     * Send message using Meta Cloud API.
     */
    private function sendWhatsApp(string $phoneNumberId, string $accessToken, string $toPhone, string $message)
    {
        $url = "https://graph.facebook.com/v19.0/{$phoneNumberId}/messages";

        $response = Http::withToken($accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $toPhone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ]);

        if ($response->failed()) {
            Log::error('SendPaymentReminders: Failed to send WA message: ' . $response->body());
        }
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
}
