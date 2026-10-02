<?php

namespace App\Http\Controllers;

use App\Models\AgentSiteVisit;
use App\Models\BrokerCompany;
use App\Models\Project;
use App\Models\ProjectDutySchedule;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentVisitController extends Controller
{
    /**
     * Compute comprehensive agent visit traffic reports and statistics.
     */
    public static function getTrafficAnalytics(int $projectId, ?int $month = null, ?int $year = null)
    {
        $month = $month ?? (int)now()->format('m');
        $year = $year ?? (int)now()->format('Y');

        $startOfMonth = Carbon::create($year, $month, 1)->startOfMonth();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $today = now()->toDateString();
        $startOfWeek = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY)->toDateString();

        $baseQuery = AgentSiteVisit::where('project_id', $projectId);

        // Counts
        $todayVisitsCount = (clone $baseQuery)->whereDate('visit_date', $today)->count();
        $weekVisitsCount = (clone $baseQuery)->whereBetween('visit_date', [$startOfWeek, $endOfWeek])->count();
        $monthVisitsCount = (clone $baseQuery)->whereBetween('visit_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])->count();

        // Purpose breakdown for the month
        $monthlyPurposes = (clone $baseQuery)
            ->whereBetween('visit_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->select('purpose', DB::raw('count(*) as total'))
            ->groupBy('purpose')
            ->pluck('total', 'purpose')
            ->toArray();

        // Top Active Agents (Leaderboard Keaktifan Agen)
        $topAgents = (clone $baseQuery)
            ->whereBetween('visit_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->select(
                'agent_name',
                'agent_phone',
                'agency_name',
                'agent_id',
                DB::raw('count(*) as total_visits'),
                DB::raw("sum(case when purpose = 'pk' then 1 else 0 end) as pk_count"),
                DB::raw("sum(case when purpose = 'konten' then 1 else 0 end) as konten_count"),
                DB::raw("sum(case when purpose = 'survey_klien' then 1 else 0 end) as survey_count"),
                DB::raw('max(visit_date) as last_visit')
            )
            ->groupBy('agent_name', 'agent_phone', 'agency_name', 'agent_id')
            ->orderByDesc('total_visits')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                return [
                    'agent_name' => $row->agent_name,
                    'agent_phone' => $row->agent_phone,
                    'agency_name' => $row->agency_name ?: 'Mitra Freelance',
                    'agent_id' => $row->agent_id,
                    'total_visits' => (int)$row->total_visits,
                    'pk_count' => (int)$row->pk_count,
                    'konten_count' => (int)$row->konten_count,
                    'survey_count' => (int)$row->survey_count,
                    'last_visit' => $row->last_visit ? Carbon::parse($row->last_visit)->locale('id')->isoFormat('D MMM Y') : '-',
                ];
            });

        // Top Visiting Agencies
        $topAgencies = (clone $baseQuery)
            ->whereBetween('visit_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->whereNotNull('agency_name')
            ->where('agency_name', '!=', '')
            ->select('agency_name', DB::raw('count(*) as total_visits'), DB::raw('count(distinct agent_name) as agent_count'))
            ->groupBy('agency_name')
            ->orderByDesc('total_visits')
            ->limit(6)
            ->get();

        // 14-Day Traffic Trend
        $twoWeeksAgo = now()->subDays(13)->toDateString();
        $recentTrendRaw = (clone $baseQuery)
            ->whereBetween('visit_date', [$twoWeeksAgo, $today])
            ->select(
                'visit_date',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when purpose = 'pk' then 1 else 0 end) as pk"),
                DB::raw("sum(case when purpose = 'konten' then 1 else 0 end) as konten"),
                DB::raw("sum(case when purpose = 'survey_klien' then 1 else 0 end) as survey"),
                DB::raw("sum(case when purpose not in ('pk', 'konten', 'survey_klien') then 1 else 0 end) as other")
            )
            ->groupBy('visit_date')
            ->get()
            ->keyBy(fn($r) => Carbon::parse($r->visit_date)->toDateString());

        $period = CarbonPeriod::create($twoWeeksAgo, $today);
        $dailyTrend = [];
        foreach ($period as $dt) {
            $dStr = $dt->toDateString();
            $found = $recentTrendRaw->get($dStr);
            $dailyTrend[] = [
                'date' => $dStr,
                'day_name' => $dt->locale('id')->isoFormat('dd'),
                'date_formatted' => $dt->locale('id')->isoFormat('D MMM'),
                'is_today' => $dStr === $today,
                'total' => (int)($found?->total ?? 0),
                'pk' => (int)($found?->pk ?? 0),
                'konten' => (int)($found?->konten ?? 0),
                'survey' => (int)($found?->survey ?? 0),
                'other' => (int)($found?->other ?? 0),
            ];
        }

        return [
            'today_visits_count' => $todayVisitsCount,
            'week_visits_count' => $weekVisitsCount,
            'month_visits_count' => $monthVisitsCount,
            'monthly_purposes' => [
                'pk' => (int)($monthlyPurposes['pk'] ?? 0),
                'konten' => (int)($monthlyPurposes['konten'] ?? 0),
                'survey_klien' => (int)($monthlyPurposes['survey_klien'] ?? 0),
                'koordinasi' => (int)($monthlyPurposes['koordinasi'] ?? 0),
                'lainnya' => (int)($monthlyPurposes['lainnya'] ?? 0),
            ],
            'top_agents' => $topAgents,
            'top_agencies' => $topAgencies,
            'daily_trend' => $dailyTrend,
        ];
    }

    /**
     * Store a new agent site visit (Buku Tamu Kedatangan Agen).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'visit_date' => 'required|date',
            'visit_time' => 'nullable|string|max:10',
            'agent_id' => 'nullable|exists:users,id',
            'agent_name' => 'required_without:agent_id|nullable|string|max:255',
            'agent_phone' => 'nullable|string|max:50',
            'agency_name' => 'nullable|string|max:255',
            'broker_company_id' => 'nullable|exists:broker_companies,id',
            'agent_type' => 'nullable|string|max:50',
            'purpose' => 'required|in:pk,konten,survey_klien,koordinasi,lainnya',
            'visitor_count' => 'nullable|integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
            'documentation_url' => 'nullable|string|max:500',
        ]);

        $currentUser = auth()->user();
        $visitDate = Carbon::parse($validated['visit_date'])->toDateString();
        $visitTime = $validated['visit_time'] ?: now()->format('H:i');

        // Resolve agent details if selected from system
        $agentUser = null;
        if (!empty($validated['agent_id'])) {
            $agentUser = User::with(['brokerCompany', 'masterLead'])->find($validated['agent_id']);
        }

        $agentName = $agentUser?->name ?? ($validated['agent_name'] ?? null);
        $agentPhone = $agentUser?->phone ?? ($validated['agent_phone'] ?? null);
        $brokerCompanyId = $agentUser?->broker_company_id ?? ($validated['broker_company_id'] ?? null);
        
        $agencyName = $validated['agency_name'] ?? null;
        if (!$agencyName && $agentUser) {
            if ($agentUser->brokerCompany) {
                $agencyName = $agentUser->brokerCompany->name;
            } elseif ($agentUser->masterLead) {
                $agencyName = "Sub-Agent ML ({$agentUser->masterLead->name})";
            } elseif (in_array($agentUser->agent_type, ['inhouse', 'inhouse_developer'])) {
                $agencyName = "In-House Developer";
            } else {
                $agencyName = "Mitra Agen";
            }
        }

        $agentType = $validated['agent_type'] ?? ($agentUser?->agent_type ?? 'agency_agent');

        // Automatically associate with today's scheduled duty if available
        $dutySchedule = ProjectDutySchedule::where('project_id', $validated['project_id'])
            ->whereDate('duty_date', $visitDate)
            ->where('status', 'active')
            ->first();

        $visit = AgentSiteVisit::create([
            'company_id' => $currentUser?->company_id,
            'project_id' => $validated['project_id'],
            'duty_schedule_id' => $dutySchedule?->id,
            'recorded_by' => $currentUser?->id,
            'agent_id' => $agentUser?->id,
            'broker_company_id' => $brokerCompanyId,
            'agent_name' => $agentName,
            'agent_phone' => $agentPhone,
            'agency_name' => $agencyName,
            'agent_type' => $agentType,
            'visit_date' => $visitDate,
            'visit_time' => $visitTime,
            'purpose' => $validated['purpose'],
            'visitor_count' => (int)($validated['visitor_count'] ?? 1),
            'notes' => $validated['notes'] ?? null,
            'documentation_url' => $validated['documentation_url'] ?? null,
            'status' => 'completed',
        ]);

        $purposeMeta = $visit->getPurposeMeta();
        $dateFormatted = Carbon::parse($visitDate)->locale('id')->isoFormat('D MMMM Y');

        return back()->with('success', "Kedatangan Agen '{$agentName}' ({$purposeMeta['label']}) pada {$dateFormatted} berhasil dicatat oleh Petugas Jaga.");
    }

    /**
     * Update an agent site visit record.
     */
    public function update(Request $request, AgentSiteVisit $agentVisit)
    {
        $validated = $request->validate([
            'visit_date' => 'required|date',
            'visit_time' => 'nullable|string|max:10',
            'agent_name' => 'required|string|max:255',
            'agent_phone' => 'nullable|string|max:50',
            'agency_name' => 'nullable|string|max:255',
            'purpose' => 'required|in:pk,konten,survey_klien,koordinasi,lainnya',
            'visitor_count' => 'nullable|integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
            'documentation_url' => 'nullable|string|max:500',
        ]);

        $agentVisit->update($validated);

        return back()->with('success', "Data kunjungan agen '{$agentVisit->agent_name}' berhasil diperbarui.");
    }

    /**
     * Delete an agent site visit record.
     */
    public function destroy(AgentSiteVisit $agentVisit)
    {
        $name = $agentVisit->agent_name;
        $agentVisit->delete();

        return back()->with('success', "Catatan kunjungan agen '{$name}' berhasil dihapus.");
    }

    /**
     * JSON / API endpoint for traffic report data.
     */
    public function report(Request $request)
    {
        $projectId = (int)($request->project_id ?? Project::first()?->id ?? 1);
        $month = (int)($request->month ?? now()->format('m'));
        $year = (int)($request->year ?? now()->format('Y'));

        $analytics = static::getTrafficAnalytics($projectId, $month, $year);

        return response()->json($analytics);
    }
}
