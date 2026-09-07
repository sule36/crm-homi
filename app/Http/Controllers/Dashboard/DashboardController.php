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
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Stats Overview (Cached for 5 minutes)
        $stats = Cache::remember('dashboard_stats_' . $user->id, 300, function () {
            $hasTransactions = Schema::hasTable('transactions');
            $hasBookings = Schema::hasTable('bookings');

            return [
                'total_projects' => Schema::hasTable('projects') ? Project::count() : 0,
                'total_units' => Schema::hasTable('units') ? Unit::count() : 0,
                'available_units' => Schema::hasTable('units') ? Unit::where('status', 'available')->count() : 0,
                'sold_units' => Schema::hasTable('units') ? Unit::where('status', 'sold')->count() : 0,
                'booked_units' => Schema::hasTable('units') ? Unit::where('status', 'booked')->count() : 0,
                'total_leads' => Schema::hasTable('leads') ? Lead::count() : 0,
                'active_leads' => Schema::hasTable('leads') ? Lead::whereNotIn('status', ['won', 'lost'])->count() : 0,
                'hot_leads' => Schema::hasTable('leads') ? Lead::where('score', '>=', 60)->count() : 0,
                'total_revenue' => $hasTransactions ? (Transaction::sum('amount') ?? 0) : 0,
                'this_month_revenue' => $hasTransactions ? (Transaction::whereMonth('created_at', now()->month)->sum('amount') ?? 0) : 0,
                'conversion_rate' => Schema::hasTable('leads') && Lead::count() > 0
                    ? round((Lead::where('status', 'won')->count() / Lead::count()) * 100, 1)
                    : 0,
                'pending_bookings' => $hasBookings ? Booking::where('status', 'pending')->count() : 0,
            ];
        });

        // Lead Pipeline
        $pipeline = Cache::remember('dashboard_pipeline', 300, function () {
            if (!Schema::hasTable('leads')) return [];
            return Lead::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();
        });

        // Recent Leads (Null safe)
        $recentLeads = [];
        if (Schema::hasTable('leads')) {
            $recentLeads = Lead::with(['assignedTo', 'project'])
                ->latest()
                ->take(8)
                ->get()
                ->map(fn ($lead) => [
                    'id' => $lead->id,
                    'name' => $lead->name ?? 'Tanpa Nama',
                    'phone' => $lead->phone ?? '-',
                    'status' => $lead->status ?? 'new',
                    'score' => $lead->score ?? 0,
                    'source' => $lead->source ?? 'direct',
                    'project' => $lead->project?->name ?? '-',
                    'assigned_to' => $lead->assignedTo?->name ?? '-',
                    'created_at' => $lead->created_at ? $lead->created_at->diffForHumans() : 'baru saja',
                ]);
        }

        // Today's Follow-ups
        $todayReminders = [];
        if (Schema::hasTable('follow_up_reminders')) {
            $todayReminders = FollowUpReminder::with(['lead', 'user'])
                ->where('user_id', $user->id)
                ->whereDate('remind_at', today())
                ->where('status', 'pending')
                ->get();
        }

        // Unit Status by Project
        $projectStats = Cache::remember('dashboard_project_stats', 300, function () {
            if (!Schema::hasTable('projects')) return [];
            return Project::select('id', 'name', 'code', 'total_units', 'sold_units', 'booked_units', 'available_units')
                ->where('status', 'active')
                ->get();
        });

        // Monthly Revenue Trend (last 6 months - with MySQL strict mode fix & fallback)
        $revenueTrend = Cache::remember('dashboard_revenue_trend', 300, function () {
            if (!Schema::hasTable('transactions')) return [];

            try {
                $isSqlite = DB::getDriverName() === 'sqlite';
                $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

                return Transaction::select(
                        DB::raw("{$dateFormat} as month"),
                        DB::raw('SUM(amount) as total')
                    )
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->groupBy(DB::raw($dateFormat))
                    ->orderBy(DB::raw($dateFormat))
                    ->get();
            } catch (\Throwable $e) {
                return [];
            }
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
