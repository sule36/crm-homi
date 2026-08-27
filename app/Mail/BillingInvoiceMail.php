<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\PaymentSchedule;
use App\Models\Setting;

class BillingInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public PaymentSchedule $schedule;
    public string $reminderType; // 'manual', 'h_3', 'h_0', 'h_plus_7'
    public array $bankInfo;
    public string $companyName;
    public ?string $companyLogo;

    /**
     * Create a new message instance.
     */
    public function __construct(PaymentSchedule $schedule, string $reminderType = 'manual')
    {
        $this->schedule = $schedule->loadMissing(['booking.lead', 'booking.unit.project', 'booking.unit.unitType']);
        $this->reminderType = $reminderType;
        
        $this->bankInfo = Setting::get('spr_bank_info', [
            'bank_name' => 'BCA / BSI',
            'account_number' => '542-539-2929 / 732-694-3422',
            'account_holder' => 'PT. Serangkai Roden Development',
        ]);

        $this->companyName = Setting::get('company_name', 'Homi Developer');
        $this->companyLogo = Setting::get('company_logo', null);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $unitCode = ($this->schedule->booking?->unit?->block ?? '') . ($this->schedule->booking?->unit?->number ?? '');
        $label = $this->schedule->label;
        $customerName = $this->schedule->booking?->lead?->name ?? 'Pelanggan';

        $subject = match ($this->reminderType) {
            'h_3' => "[PENGINGAT H-3] Tagihan {$label} Unit {$unitCode} - {$this->companyName}",
            'h_0' => "[JATUH TEMPO HARI INI] Tagihan {$label} Unit {$unitCode} - {$this->companyName}",
            'h_plus_7' => "[PERINGATAN KETERLAMBATAN] Tagihan {$label} Unit {$unitCode} - {$this->companyName}",
            default => "INVOICE TAGIHAN {$label} - Unit {$unitCode} ({$customerName})",
        };

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.billing_invoice',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
