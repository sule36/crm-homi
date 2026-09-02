<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected static function booted()
    {
        static::saved(function ($commission) {
            if ($commission->status === 'paid') {
                $commission->loadMissing(['user', 'brokerCompany', 'booking.unit.project']);
                $booking = $commission->booking;
                $projectId = $booking?->project_id;
                $recipientName = $commission->payout_recipient === 'agency'
                    ? ($commission->brokerCompany?->name ?? $commission->user?->brokerCompany?->name ?? 'Agency Broker')
                    : ($commission->user?->name ?? 'Agent');
                $unitCode = $booking?->unit ? "Unit {$booking->unit->block}{$booking->unit->number}" : "Unit Booking #{$booking?->id}";

                // Check if GL entry already recorded for this commission
                $existingGl = GeneralLedger::where('reference_type', static::class)
                    ->where('reference_id', $commission->id)
                    ->first();

                if (!$existingGl) {
                    $glAmount = ($commission->payout_recipient === 'master_lead' && $commission->base_commission > 0)
                        ? (int) $commission->base_commission
                        : (int) $commission->amount;

                    GeneralLedger::recordEntry(
                        type: 'expense',
                        category: 'commission',
                        amount: $glAmount,
                        reference: $commission,
                        projectId: $projectId,
                        description: "Pembayaran Komisi ({$commission->payout_recipient}) kepada {$recipientName} - {$unitCode}",
                        date: $commission->paid_at ?? now(),
                        recordedBy: auth()->id(),
                        bankAccountId: $commission->bank_account_id,
                    );
                }
            }
        });

        static::deleted(function ($commission) {
            GeneralLedger::removeForReference($commission);
        });
    }

    protected $fillable = [
        'user_id', 'broker_company_id', 'booking_id', 'amount',
        'base_commission', 'promo_bonus', 'rate_used', 'payout_recipient',
        'status', 'paid_at', 'notes', 'receipt_number', 'bank_account_id',
        'ml_payout_status', 'ml_paid_at', 'ml_receipt_number',
        'master_lead_invoice_id', 'claim_type', 'reward_name'
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function brokerCompany() { return $this->belongsTo(BrokerCompany::class); }
    public function booking() { return $this->belongsTo(Booking::class); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
    public function masterLeadInvoice() { return $this->belongsTo(MasterLeadInvoice::class, 'master_lead_invoice_id'); }
}
