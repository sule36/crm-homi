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
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // 1. Stats Overview
        $stats = [
            'total_projects' => 0,
            'total_units' => 0,
            'available_units' => 0,
            'sold_units' => 0,
            'booked_units' => 0,
            'total_leads' => 0,
            'active_leads' => 0,
            'hot_leads' => 0,
            'total_revenue' => 0,
            'this_month_revenue' => 0,
            'conversion_rate' => 0,
            'pending_bookings' => 0,
        ];

        try {
            if (Schema::hasTable('projects')) {
                $stats['total_projects'] = Project::count();
            }
            if (Schema::hasTable('units')) {
                $stats['total_units'] = Unit::count();
                $stats['available_units'] = Unit::where('status', 'available')->count();
                $stats['sold_units'] = Unit::where('status', 'sold')->count();
                $stats['booked_units'] = Unit::where('status', 'booked')->count();
            }
            if (Schema::hasTable('leads')) {
                $totalLeads = Lead::count();
                $stats['total_leads'] = $totalLeads;
                $stats['active_leads'] = Lead::whereNotIn('status', ['won', 'lost'])->count();
                $stats['hot_leads'] = Lead::where('score', '>=', 60)->count();
                if ($totalLeads > 0) {
                    $wonLeads = Lead::where('status', 'won')->count();
                    $stats['conversion_rate'] = round(($wonLeads / $totalLeads) * 100, 1);
                }
            }
            if (Schema::hasTable('transactions')) {
                $stats['total_revenue'] = (float) (Transaction::sum('amount') ?? 0);
                $stats['this_month_revenue'] = (float) (Transaction::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('amount') ?? 0);
            }
            if (Schema::hasTable('bookings')) {
                $stats['pending_bookings'] = Booking::where('status', 'pending')->count();
            }
        } catch (\Throwable $e) {
            // Safe fallback
        }

        // 2. Lead Pipeline
        $pipeline = [];
        try {
            if (Schema::hasTable('leads')) {
                $pipeline = Lead::select('status', DB::raw('count(*) as total'))
                    ->groupBy('status')
                    ->pluck('total', 'status')
                    ->toArray();
            }
        } catch (\Throwable $e) {
            $pipeline = [];
        }

        // 3. Recent Leads
        $recentLeads = [];
        try {
            if (Schema::hasTable('leads')) {
                $recentLeads = Lead::with(['assignedTo', 'project'])
                    ->latest()
                    ->take(8)
                    ->get()
                    ->map(function ($lead) {
                        return [
                            'id' => $lead->id,
                            'name' => $lead->name ?? 'Tanpa Nama',
                            'phone' => $lead->phone ?? '-',
                            'status' => $lead->status ?? 'new',
                            'score' => $lead->score ?? 0,
                            'source' => $lead->source ?? 'direct',
                            'project' => $lead->project?->name ?? '-',
                            'assigned_to' => $lead->assignedTo?->name ?? '-',
                            'created_at' => $lead->created_at ? $lead->created_at->diffForHumans() : 'baru saja',
                        ];
                    })
                    ->toArray();
            }
        } catch (\Throwable $e) {
            $recentLeads = [];
        }

        // 4. Today's Follow-ups
        $todayReminders = [];
        try {
            if (Schema::hasTable('follow_up_reminders')) {
                $todayReminders = FollowUpReminder::with(['lead', 'user'])
                    ->where('user_id', $user->id)
                    ->whereDate('remind_at', today())
                    ->where('status', 'pending')
                    ->get();
            }
        } catch (\Throwable $e) {
            $todayReminders = [];
        }

        // 5. Unit Status by Project
        $projectStats = [];
        try {
            if (Schema::hasTable('projects')) {
                $projectStats = Project::select('id', 'name', 'code', 'total_units', 'sold_units', 'booked_units', 'available_units')
                    ->where('status', 'active')
                    ->get();
            }
        } catch (\Throwable $e) {
            $projectStats = [];
        }

        // 6. Monthly Revenue Trend (last 6 months)
        $revenueTrend = [];
        try {
            if (Schema::hasTable('transactions')) {
                $isSqlite = DB::getDriverName() === 'sqlite';
                $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

                $revenueTrend = Transaction::select(
                        DB::raw("{$dateFormat} as month"),
                        DB::raw('SUM(amount) as total')
                    )
                    ->where('created_at', '>=', now()->subMonths(6))
                    ->groupBy(DB::raw($dateFormat))
                    ->orderBy(DB::raw($dateFormat))
                    ->get();
            }
        } catch (\Throwable $e) {
            $revenueTrend = [];
        }

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
