<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected static function booted()
    {
        static::created(function ($transaction) {
            $booking = $transaction->booking;
            $projectId = $booking?->project_id;
            $consumerName = $booking?->lead?->name ?? 'Customer';
            
            GeneralLedger::recordEntry(
                type: 'income',
                category: 'customer_payment',
                amount: (int) $transaction->amount,
                reference: $transaction,
                projectId: $projectId,
                description: "Pembayaran dari {$consumerName}",
                date: $transaction->created_at,
                recordedBy: $transaction->recorded_by,
                bankAccountId: $transaction->bank_account_id,
            );
        });

        static::deleted(function ($transaction) {
            GeneralLedger::removeForReference($transaction);
        });
    }

    protected $fillable = [
        'booking_id', 'payment_schedule_id', 'amount',
        'payment_method', 'bank_name', 'reference_number',
        'receipt_file', 'notes', 'recorded_by', 'bank_account_id',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function booking() { return $this->belongsTo(Booking::class); }
    public function paymentSchedule() { return $this->belongsTo(PaymentSchedule::class); }
    public function recordedBy() { return $this->belongsTo(User::class, 'recorded_by'); }
    public function bankAccount() { return $this->belongsTo(BankAccount::class); }
}
