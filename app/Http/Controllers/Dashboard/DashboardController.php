<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Unit;
use App\Models\Transaction;
use App\Models\FollowUpReminder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Stats Overview (Cached for 1 hour)
        $stats = Cache::remember('dashboard_stats_' . $user->id, 3600, function () {
            return [
                'total_projects' => Project::count(),
                'total_units' => Unit::count(),
                'available_units' => Unit::where('status', 'available')->count(),
                'sold_units' => Unit::where('status', 'sold')->count(),
                'booked_units' => Unit::where('status', 'booked')->count(),
                'total_leads' => Lead::count(),
                'active_leads' => Lead::whereNotIn('status', ['won', 'lost'])->count(),
                'hot_leads' => Lead::where('score', '>=', 60)->count(),
                'total_revenue' => Transaction::sum('amount'),
                'this_month_revenue' => Transaction::whereMonth('created_at', now()->month)->sum('amount'),
                'conversion_rate' => Lead::count() > 0
                    ? round((Lead::where('status', 'won')->count() / Lead::count()) * 100, 1)
                    : 0,
                'pending_bookings' => Booking::where('status', 'pending')->count(),
            ];
        });

        // Lead Pipeline
        $pipeline = Cache::remember('dashboard_pipeline', 3600, function () {
            return Lead::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
        });

        // Recent Leads
        $recentLeads = Lead::with(['assignedTo', 'project'])
            ->latest()
            ->take(8)
            ->get()
            ->map(fn ($lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'phone' => $lead->phone,
                'status' => $lead->status,
                'score' => $lead->score,
                'source' => $lead->source,
                'project' => $lead->project?->name,
                'assigned_to' => $lead->assignedTo?->name,
                'created_at' => $lead->created_at->diffForHumans(),
            ]);

        // Today's Follow-ups
        $todayReminders = FollowUpReminder::with(['lead', 'user'])
            ->where('user_id', $user->id)
            ->whereDate('remind_at', today())
            ->where('status', 'pending')
            ->get();

        // Unit Status by Project
        $projectStats = Cache::remember('dashboard_project_stats', 3600, function () {
            return Project::select('id', 'name', 'code', 'total_units', 'sold_units', 'booked_units', 'available_units')
                ->where('status', 'active')
                ->get();
        });

        // Monthly Revenue Trend (last 6 months)
        $revenueTrend = Cache::remember('dashboard_revenue_trend', 3600, function () {
            $isSqlite = DB::getDriverName() === 'sqlite';
            $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

            return Transaction::select(
                    DB::raw("{$dateFormat} as month"),
                    DB::raw('SUM(amount) as total')
                )
                ->where('created_at', '>=', now()->subMonths(6))
                ->groupBy('month')
                ->orderBy('month')
                ->get();
        });

        return Inertia::render('Dashboard/Index', [
            'stats' => $stats,
            'pipeline' => $pipeline,
            'recentLeads' => $recentLeads,
            'todayReminders' => $todayReminders,
            'projectStats' => $projectStats,
            'revenueTrend' => $revenueTrend,
        ]);
    }
}
