<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use BelongsToTenant;
    protected static function booted()
    {
        static::created(function ($transaction) {
            $booking = $transaction->booking ?? \App\Models\Booking::find($transaction->booking_id);
            $projectId = $booking?->project_id;
            $consumerName = $booking?->lead?->name ?? 'Customer';
            $entryDate = $transaction->created_at ? $transaction->created_at->format('Y-m-d') : now()->format('Y-m-d');
            
            GeneralLedger::recordEntry(
                type: 'income',
                category: 'customer_payment',
                amount: (int) $transaction->amount,
                reference: $transaction,
                projectId: $projectId,
                description: "Pembayaran dari {$consumerName}",
                date: $entryDate,
                recordedBy: $transaction->recorded_by,
                bankAccountId: $transaction->bank_account_id,
            );
        });

        static::updated(function ($transaction) {
            GeneralLedger::removeForReference($transaction);
            $booking = $transaction->booking ?? \App\Models\Booking::find($transaction->booking_id);
            $projectId = $booking?->project_id;
            $consumerName = $booking?->lead?->name ?? 'Customer';
            $entryDate = $transaction->created_at ? $transaction->created_at->format('Y-m-d') : now()->format('Y-m-d');
            
            GeneralLedger::recordEntry(
                type: 'income',
                category: 'customer_payment',
                amount: (int) $transaction->amount,
                reference: $transaction,
                projectId: $projectId,
                description: "Pembayaran dari {$consumerName}",
                date: $entryDate,
                recordedBy: $transaction->recorded_by,
                bankAccountId: $transaction->bank_account_id,
            );
        });

        static::deleted(function ($transaction) {
            GeneralLedger::removeForReference($transaction);
        });
    }

    protected $fillable = [
        'company_id', 'booking_id', 'payment_schedule_id', 'amount',
        'payment_method', 'bank_name', 'reference_number',
        'receipt_file', 'wet_receipt_file', 'receipt_number', 'notes', 'recorded_by', 'bank_account_id',
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
