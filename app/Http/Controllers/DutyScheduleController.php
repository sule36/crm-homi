<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\ProjectDutySchedule;
use App\Models\Setting;
use App\Models\User;
use App\Models\BrokerCompany;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DutyScheduleController extends Controller
{
    /**
     * Get candidate agents for duty scheduling (In-House, Master Lead sub-agents, and agency agents).
     */
    public static function getCandidateAgents(?int $companyId = null)
    {
        $companyId = $companyId ?? (auth()->check() ? auth()->user()->company_id : null);

        $agents = User::with(['brokerCompany:id,name,code', 'masterLead:id,name', 'roles'])
            ->when($companyId, fn($q) => $q->where('company_id', $companyId))
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNotNull('agent_type')
                  ->orWhereNotNull('master_lead_id')
                  ->orWhereNotNull('broker_company_id')
                  ->orWhereHas('roles', fn($rq) => $rq->whereIn('name', ['sales_agent', 'broker', 'sales_manager', 'master_lead']));
            })
            ->orderBy('name')
            ->get();

        return $agents->map(function ($agent) {
            $isMasterLeadSubAgent = !empty($agent->master_lead_id) || $agent->agent_type === 'master_lead';
            $isInhouse = in_array($agent->agent_type, ['inhouse', 'inhouse_developer', 'inhouse_master_lead']) || 
                         (!$agent->broker_company_id && !$agent->master_lead_id && $agent->hasRole('sales_agent'));
            
            $category = 'agency';
            $categoryLabel = 'Mitra Agency / Freelance';
            $badgeColor = 'amber';

            if ($isInhouse) {
                $category = 'inhouse';
                $categoryLabel = 'Sales In-House Developer';
                $badgeColor = 'emerald';
            } elseif ($isMasterLeadSubAgent) {
                $category = 'master_lead_sub_agent';
                $mlName = $agent->masterLead?->name ?? 'Master Lead';
                $categoryLabel = "Sub-Agent ML ({$mlName})";
                $badgeColor = 'purple';
            }

            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'agent_type' => $agent->agent_type,
                'category' => $category,
                'category_label' => $categoryLabel,
                'badge_color' => $badgeColor,
                'broker_company_id' => $agent->broker_company_id,
                'broker_company_name' => $agent->brokerCompany?->name,
                'master_lead_id' => $agent->master_lead_id,
                'master_lead_name' => $agent->masterLead?->name,
            ];
        });
    }

    /**
     * Display duty schedules management page / JSON endpoint.
     */
    public function index(Request $request)
    {
        $projects = Project::select('id', 'name', 'code')->get();
        $selectedProjectId = (int)($request->project_id ?? ($projects->first()?->id ?? 0));
        $selectedMonth = (int)($request->month ?? now()->format('m'));
        $selectedYear = (int)($request->year ?? now()->format('Y'));

        $schedulesQuery = ProjectDutySchedule::with([
            'user:id,name,email,phone,agent_type,broker_company_id,master_lead_id',
            'user.brokerCompany:id,name,code',
            'user.masterLead:id,name',
            'project:id,name,code'
        ])
        ->when($selectedProjectId, fn($q) => $q->where('project_id', $selectedProjectId))
        ->whereYear('duty_date', $selectedYear)
        ->whereMonth('duty_date', $selectedMonth)
        ->orderBy('duty_date')
        ->orderBy('shift');

        $schedules = $schedulesQuery->get()->map(function ($s) {
            $isMasterLeadSubAgent = !empty($s->user?->master_lead_id) || $s->user?->agent_type === 'master_lead';
            $isInhouse = in_array($s->user?->agent_type, ['inhouse', 'inhouse_developer', 'inhouse_master_lead']) || 
                         (!$s->user?->broker_company_id && !$s->user?->master_lead_id);

            return [
                'id' => $s->id,
                'project_id' => $s->project_id,
                'project_name' => $s->project?->name,
                'user_id' => $s->user_id,
                'duty_date' => $s->duty_date->format('Y-m-d'),
                'day_name' => $s->duty_date->locale('id')->isoFormat('dddd'),
                'date_formatted' => $s->duty_date->locale('id')->isoFormat('D MMM Y'),
                'shift' => $s->shift,
                'shift_label' => match($s->shift) {
                    'pagi' => 'Shift 1 (Pagi - Siang)',
                    'siang' => 'Shift 2 (Siang - Malam)',
                    default => 'Full Day (Tutup Kantor)',
                },
                'status' => $s->status,
                'notes' => $s->notes,
                'is_today' => $s->duty_date->isToday(),
                'user' => [
                    'id' => $s->user?->id,
                    'name' => $s->user?->name ?? 'Belum Ditentukan',
                    'email' => $s->user?->email,
                    'phone' => $s->user?->phone,
                    'category' => $isInhouse ? 'inhouse' : ($isMasterLeadSubAgent ? 'master_lead_sub_agent' : 'agency'),
                    'category_label' => $isInhouse ? 'Sales In-House Developer' : ($isMasterLeadSubAgent ? 'Sub-Agent Master Lead' : 'Mitra Agency'),
                    'badge_color' => $isInhouse ? 'emerald' : ($isMasterLeadSubAgent ? 'purple' : 'amber'),
                    'master_lead_name' => $s->user?->masterLead?->name,
                    'broker_company_name' => $s->user?->brokerCompany?->name,
                ]
            ];
        });

        // Today's active duty agents for each project
        $todayAgents = [];
        foreach ($projects as $proj) {
            $da = ProjectDutySchedule::getDutyAgent($proj->id);
            if ($da) {
                $todayAgents[$proj->id] = [
                    'id' => $da->id,
                    'name' => $da->name,
                    'phone' => $da->phone,
                    'agent_type' => $da->agent_type,
                    'master_lead_name' => $da->masterLead?->name,
                ];
            }
        }

        $candidateAgents = static::getCandidateAgents();

        if ($request->wantsJson()) {
            return response()->json([
                'schedules' => $schedules,
                'todayAgents' => $todayAgents,
                'candidateAgents' => $candidateAgents,
            ]);
        }

        return Inertia::render('DutySchedules/Index', [
            'projects' => $projects,
            'selectedProjectId' => $selectedProjectId,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'schedules' => $schedules,
            'todayAgents' => $todayAgents,
            'candidateAgents' => $candidateAgents,
        ]);
    }

    /**
     * Set duty schedule for a single day (Harian).
     */
    public function setDailyDuty(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
            'duty_date' => 'required|date',
            'shift' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $companyId = auth()->user()?->company_id ?? 1;
        $user = User::findOrFail($validated['user_id']);
        $shift = $validated['shift'] ?? 'full_day';
        $dutyDate = Carbon::parse($validated['duty_date'])->toDateString();

        ProjectDutySchedule::updateOrCreate(
            [
                'project_id' => $validated['project_id'],
                'duty_date' => $dutyDate,
                'shift' => $shift,
            ],
            [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => 'active',
                'notes' => $validated['notes'] ?? 'Ditetapkan via Jadwal Harian',
            ]
        );

        if ($dutyDate === now()->toDateString()) {
            Setting::set("duty_agent_{$validated['project_id']}", $user->id);
        }

        $project = Project::find($validated['project_id']);
        $dateFormatted = Carbon::parse($dutyDate)->locale('id')->isoFormat('D MMMM Y');

        return back()->with('success', "Petugas Jaga ({$project?->name} - {$dateFormatted}) berhasil ditetapkan: {$user->name}.");
    }

    /**
     * Set duty schedule for a week (Mingguan).
     * Supports:
     * 1) 'single': Assign 1 agent for all days in the week range.
     * 2) 'custom': Assign distinct agents for each day (Monday to Sunday).
     */
    public function setWeeklyDuty(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'mode' => 'required|in:single,custom',
            'user_id' => 'required_if:mode,single|nullable|exists:users,id',
            'start_date' => 'required_if:mode,single|nullable|date',
            'end_date' => 'required_if:mode,single|nullable|date|after_or_equal:start_date',
            'shift' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
            'schedules' => 'required_if:mode,custom|nullable|array|min:1',
            'schedules.*.date' => 'required_with:schedules|date',
            'schedules.*.user_id' => 'required_with:schedules|exists:users,id',
            'schedules.*.shift' => 'nullable|string|max:50',
            'schedules.*.notes' => 'nullable|string|max:500',
        ]);

        $companyId = auth()->user()?->company_id ?? 1;
        $project = Project::findOrFail($validated['project_id']);
        $updatedCount = 0;

        DB::transaction(function () use ($validated, $companyId, &$updatedCount) {
            if ($validated['mode'] === 'single') {
                $user = User::findOrFail($validated['user_id']);
                $period = CarbonPeriod::create($validated['start_date'], $validated['end_date']);
                $shift = $validated['shift'] ?? 'full_day';

                foreach ($period as $dt) {
                    $dateStr = $dt->toDateString();
                    ProjectDutySchedule::updateOrCreate(
                        [
                            'project_id' => $validated['project_id'],
                            'duty_date' => $dateStr,
                            'shift' => $shift,
                        ],
                        [
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'status' => 'active',
                            'notes' => $validated['notes'] ?? 'Ditetapkan via Jadwal Mingguan (Single Agent)',
                        ]
                    );

                    if ($dateStr === now()->toDateString()) {
                        Setting::set("duty_agent_{$validated['project_id']}", $user->id);
                    }
                    $updatedCount++;
                }
            } else {
                foreach ($validated['schedules'] as $item) {
                    $dateStr = Carbon::parse($item['date'])->toDateString();
                    $shift = $item['shift'] ?? 'full_day';

                    ProjectDutySchedule::updateOrCreate(
                        [
                            'project_id' => $validated['project_id'],
                            'duty_date' => $dateStr,
                            'shift' => $shift,
                        ],
                        [
                            'company_id' => $companyId,
                            'user_id' => $item['user_id'],
                            'status' => 'active',
                            'notes' => $item['notes'] ?? 'Ditetapkan via Roster Mingguan',
                        ]
                    );

                    if ($dateStr === now()->toDateString()) {
                        Setting::set("duty_agent_{$validated['project_id']}", $item['user_id']);
                    }
                    $updatedCount++;
                }
            }
        });

        return back()->with('success', "Jadwal Jaga Mingguan ({$project->name}) berhasil disimpan untuk {$updatedCount} hari.");
    }

    /**
     * Set duty schedule for a month (Bulanan).
     * Supports:
     * 1) 'rotation': Round-robin rotation among selected agents across the entire month.
     * 2) 'bulk': Bulk assign 1 agent to multiple selected dates in the month.
     */
    public function setMonthlyDuty(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2099',
            'mode' => 'required|in:rotation,bulk',
            'user_ids' => 'required_if:mode,rotation|nullable|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'rotation_pattern' => 'required_if:mode,rotation|nullable|in:daily,weekly',
            'skip_weekends' => 'nullable|boolean',
            'user_id' => 'required_if:mode,bulk|nullable|exists:users,id',
            'dates' => 'required_if:mode,bulk|nullable|array|min:1',
            'dates.*' => 'date',
            'shift' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:500',
        ]);

        $companyId = auth()->user()?->company_id ?? 1;
        $project = Project::findOrFail($validated['project_id']);
        $shift = $validated['shift'] ?? 'full_day';
        $updatedCount = 0;

        DB::transaction(function () use ($validated, $companyId, $shift, &$updatedCount) {
            if ($validated['mode'] === 'rotation') {
                $userIds = $validated['user_ids'];
                $userCount = count($userIds);
                $pattern = $validated['rotation_pattern'] ?? 'daily';
                $skipWeekends = !empty($validated['skip_weekends']);

                $startOfMonth = Carbon::create($validated['year'], $validated['month'], 1)->startOfMonth();
                $endOfMonth = $startOfMonth->copy()->endOfMonth();
                $period = CarbonPeriod::create($startOfMonth, $endOfMonth);

                $dayIndex = 0;
                $currentWeek = -1;
                $weekIndex = -1;

                foreach ($period as $dt) {
                    if ($skipWeekends && ($dt->isSaturday() || $dt->isSunday())) {
                        continue;
                    }

                    if ($pattern === 'weekly') {
                        $weekNum = $dt->weekOfYear;
                        if ($weekNum !== $currentWeek) {
                            $currentWeek = $weekNum;
                            $weekIndex++;
                        }
                        $assignedUserId = $userIds[$weekIndex % $userCount];
                    } else {
                        $assignedUserId = $userIds[$dayIndex % $userCount];
                        $dayIndex++;
                    }

                    $dateStr = $dt->toDateString();
                    ProjectDutySchedule::updateOrCreate(
                        [
                            'project_id' => $validated['project_id'],
                            'duty_date' => $dateStr,
                            'shift' => $shift,
                        ],
                        [
                            'company_id' => $companyId,
                            'user_id' => $assignedUserId,
                            'status' => 'active',
                            'notes' => $validated['notes'] ?? 'Ditetapkan via Rotasi Bulanan Otomatis',
                        ]
                    );

                    if ($dateStr === now()->toDateString()) {
                        Setting::set("duty_agent_{$validated['project_id']}", $assignedUserId);
                    }
                    $updatedCount++;
                }
            } else {
                $user = User::findOrFail($validated['user_id']);
                foreach ($validated['dates'] as $dateStr) {
                    $cleanDate = Carbon::parse($dateStr)->toDateString();
                    ProjectDutySchedule::updateOrCreate(
                        [
                            'project_id' => $validated['project_id'],
                            'duty_date' => $cleanDate,
                            'shift' => $shift,
                        ],
                        [
                            'company_id' => $companyId,
                            'user_id' => $user->id,
                            'status' => 'active',
                            'notes' => $validated['notes'] ?? 'Ditetapkan via Bulk Tanggal Bulanan',
                        ]
                    );

                    if ($cleanDate === now()->toDateString()) {
                        Setting::set("duty_agent_{$validated['project_id']}", $user->id);
                    }
                    $updatedCount++;
                }
            }
        });

        $monthName = Carbon::create($validated['year'], $validated['month'], 1)->locale('id')->isoFormat('MMMM Y');
        return back()->with('success', "Jadwal Jaga Bulanan ({$project->name} - {$monthName}) berhasil dibentuk untuk {$updatedCount} hari.");
    }

    /**
     * Delete a duty schedule.
     */
    public function destroy(ProjectDutySchedule $dutySchedule)
    {
        $projectId = $dutySchedule->project_id;
        $dutyDate = $dutySchedule->duty_date?->format('Y-m-d');
        $dutySchedule->delete();

        if ($dutyDate === now()->toDateString()) {
            $nextSchedule = ProjectDutySchedule::where('project_id', $projectId)
                ->whereDate('duty_date', now()->toDateString())
                ->latest()
                ->first();

            if ($nextSchedule) {
                Setting::set("duty_agent_{$projectId}", $nextSchedule->user_id);
            }
        }

        return back()->with('success', "Jadwal piket tanggal {$dutyDate} berhasil dihapus.");
    }

    /**
     * Legacy quick set today action (kept for full backward compatibility).
     */
    public function setTodayDuty(Request $request)
    {
        $request->merge([
            'duty_date' => $request->duty_date ?: now()->toDateString(),
        ]);
        return $this->setDailyDuty($request);
    }

    /**
     * 1-Click quick assign duty agent to a lead.
     */
    public function assignDutyAgentToLead(Request $request, Lead $lead)
    {
        $projectId = $lead->project_id ?? $request->input('project_id');
        
        $dutyAgent = null;
        if ($projectId) {
            $dutyAgent = ProjectDutySchedule::getDutyAgent((int)$projectId);
        }

        // Fallback to requested user or candidate agents
        if (!$dutyAgent && $request->filled('user_id')) {
            $dutyAgent = User::find($request->user_id);
        }

        if (!$dutyAgent) {
            $dutyAgent = User::where('status', 'active')
                ->where(function ($q) {
                    $q->whereIn('agent_type', ['inhouse', 'inhouse_developer', 'inhouse_master_lead'])
                      ->orWhereNotNull('master_lead_id');
                })
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->first();
        }

        if (!$dutyAgent) {
            return back()->with('error', 'Belum ada Petugas Jaga yang ditetapkan untuk proyek ini. Silakan tentukan Petugas Jaga terlebih dahulu.');
        }

        $oldAgentName = $lead->assignedTo?->name ?? 'Belum Ditugaskan';

        // If it's an external broker lead, set duty agent as inhouse_pic_id to protect broker commissions
        if (in_array($lead->source, ['broker', 'agency', 'agent', 'independent'])) {
            $lead->update([
                'inhouse_pic_id' => $dutyAgent->id,
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "Sales In-House Pendamping ditetapkan ke Agen Jaga: {$dutyAgent->name} (Tamu Bawaan Broker)",
            ]);

            return back()->with('success', "Sales In-House Pendamping berhasil ditugaskan ke {$dutyAgent->name}. Hak komisi agen luar tetap aman!");
        }

        // Standard Walk-In or Direct Lead
        $lead->update([
            'assigned_to' => $dutyAgent->id,
        ]);
        $lead->recalculateScore();

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => "Lead ditugaskan ke Agen Jaga Hari Ini: {$dutyAgent->name} (sebelumnya: {$oldAgentName})",
        ]);

        return back()->with('success', "Lead berhasil ditugaskan ke Agen Jaga Hari Ini: {$dutyAgent->name}.");
    }
}
