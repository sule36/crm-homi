<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id', 'assigned_to', 'name', 'phone', 'email',
        'identity_number', 'source', 'campaign_id', 'utm_campaign', 'broker_company_id',
        'status', 'score', 'lost_reason', 'notes', 'preferences',
        'last_contacted_at',
    ];

    protected function casts(): array
    {
        return [
            'preferences' => 'array',
            'last_contacted_at' => 'datetime',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function brokerCompany()
    {
        return $this->belongsTo(BrokerCompany::class);
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class)->latest();
    }

    public function reminders()
    {
        return $this->hasMany(FollowUpReminder::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function chatMessages()
    {
        return $this->hasMany(ChatMessage::class);
    }

    // Auto-calculate score based on status + engagement
    public function recalculateScore(): void
    {
        $score = 5; // Base score for all leads

        // Status weight (biggest factor)
        $statusScores = [
            'new' => 0,
            'contacted' => 10,
            'visited' => 30,
            'negotiation' => 50,
            'booking' => 80,
            'won' => 100,
            'lost' => 0,
        ];
        $score += $statusScores[$this->status] ?? 0;

        // Activity engagement bonus (single optimized query)
        $activityCount = $this->activities()->count();
        $score += min($activityCount * 2, 20); // Max 20 bonus from activities

        // Contact info completeness
        if ($this->email) $score += 5;

        // Recency bonus — contacted within last 7 days
        if ($this->last_contacted_at && $this->last_contacted_at->gt(now()->subDays(7))) {
            $score += 5;
        }

        $this->update(['score' => min($score, 100)]);
    }
}
