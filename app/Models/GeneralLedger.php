<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GeneralLedger extends Model
{
    protected $table = 'general_ledger';

    protected $fillable = [
        'date', 'type', 'category', 'reference_type', 'reference_id',
        'project_id', 'bank_account_id', 'description', 'debit', 'credit', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    protected static function booted()
    {
        static::saved(function ($ledger) {
            if ($ledger->bank_account_id) {
                BankAccount::find($ledger->bank_account_id)?->syncBalance();
            }
        });

        static::deleted(function ($ledger) {
            if ($ledger->bank_account_id) {
                BankAccount::find($ledger->bank_account_id)?->syncBalance();
            }
        });
    }

    // Polymorphic reference to Transaction, Expense, Payroll, Commission, etc.
    public function reference()
    {
        return $this->morphTo();
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount()
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Central helper to record a ledger entry.
     *
     * @param string $type       'income' or 'expense'
     * @param string $category   e.g. 'customer_payment', 'operational_expense', 'salary', 'commission'
     * @param int    $amount     The amount in IDR (no decimals)
     * @param Model  $reference  The polymorphic source model
     * @param int|null $projectId
     * @param string $description
     * @param mixed  $date       Carbon or string date
     * @param int|null $recordedBy
     * @param int|null $bankAccountId
     */
    public static function recordEntry(
        string $type,
        string $category,
        int $amount,
        Model $reference,
        ?int $projectId,
        string $description,
        mixed $date = null,
        ?int $recordedBy = null,
        ?int $bankAccountId = null,
    ): self {
        return self::create([
            'date' => $date ?? now(),
            'type' => $type,
            'category' => $category,
            'reference_type' => get_class($reference),
            'reference_id' => $reference->id,
            'project_id' => $projectId,
            'bank_account_id' => $bankAccountId,
            'description' => $description,
            'debit' => $type === 'expense' ? $amount : 0,
            'credit' => $type === 'income' ? $amount : 0,
            'recorded_by' => $recordedBy ?? auth()->id(),
        ]);
    }

    /**
     * Remove GL entries for a given reference.
     */
    public static function removeForReference(Model $reference): void
    {
        self::where('reference_type', get_class($reference))
            ->where('reference_id', $reference->id)
            ->get()
            ->each(fn($ledger) => $ledger->delete());
    }

    // ── Scopes ────────────────────────────────────────
    public function scopeIncome($query)
    {
        return $query->where('type', 'income');
    }

    public function scopeExpense($query)
    {
        return $query->where('type', 'expense');
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }
}
