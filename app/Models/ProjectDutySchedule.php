<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class ProjectDutySchedule extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'project_id',
        'user_id',
        'duty_date',
        'shift',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'duty_date' => 'date',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the duty agent for a specific project and date (default today).
     */
    public static function getDutyAgent(int $projectId, ?string $date = null): ?User
    {
        $targetDate = $date ?: now()->toDateString();
        $schedule = static::where('project_id', $projectId)
            ->whereDate('duty_date', $targetDate)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($schedule && $schedule->user) {
            return $schedule->user;
        }

        // Fallback to setting key if any
        $settingUserId = Setting::get("duty_agent_{$projectId}");
        if ($settingUserId) {
            return User::find($settingUserId);
        }

        return null;
    }
}
