<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class AgentSiteVisit extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'company_id',
        'project_id',
        'duty_schedule_id',
        'recorded_by',
        'agent_id',
        'broker_company_id',
        'agent_name',
        'agent_phone',
        'agency_name',
        'agent_type',
        'visit_date',
        'visit_time',
        'purpose',
        'visitor_count',
        'notes',
        'documentation_url',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'visit_date' => 'date',
            'visitor_count' => 'integer',
        ];
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function dutySchedule()
    {
        return $this->belongsTo(ProjectDutySchedule::class, 'duty_schedule_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function brokerCompany()
    {
        return $this->belongsTo(BrokerCompany::class, 'broker_company_id');
    }

    public function getPurposeMeta(): array
    {
        return match ($this->purpose) {
            'pk' => [
                'label' => 'Product Knowledge (PK)',
                'short_label' => 'PK / Briefing',
                'icon' => '📚',
                'color' => 'blue',
                'badge_class' => 'bg-blue-50 text-blue-700 border-blue-200',
            ],
            'konten' => [
                'label' => 'Bikin Konten Medsos',
                'short_label' => 'Konten Medsos',
                'icon' => '🎬',
                'color' => 'purple',
                'badge_class' => 'bg-purple-50 text-purple-700 border-purple-200',
            ],
            'survey_klien' => [
                'label' => 'Survey Unit / Bawa Klien',
                'short_label' => 'Survey Klien',
                'icon' => '🏡',
                'color' => 'emerald',
                'badge_class' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            ],
            'koordinasi' => [
                'label' => 'Koordinasi / Ambil Brosur',
                'short_label' => 'Koordinasi',
                'icon' => '🤝',
                'color' => 'amber',
                'badge_class' => 'bg-amber-50 text-amber-700 border-amber-200',
            ],
            default => [
                'label' => 'Kunjungan Lainnya',
                'short_label' => 'Lainnya',
                'icon' => '📍',
                'color' => 'slate',
                'badge_class' => 'bg-slate-50 text-slate-700 border-slate-200',
            ],
        };
    }
}
