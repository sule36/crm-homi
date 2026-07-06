<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    const UPDATED_AT = null; // Only created_at, no updated_at

    protected $fillable = [
        'user_id', 'action', 'description', 'auditable_type', 'auditable_id',
        'old_values', 'new_values', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    // Helper to log an action
    public static function record(string $action, ?Model $model = null, ?array $oldValues = null, ?array $newValues = null): static
    {
        return static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $action . ($model ? ' ' . class_basename($model) . ' #' . $model->getKey() : ''),
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
