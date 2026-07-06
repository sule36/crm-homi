<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index()
    {
        // 1. Sales per Project
        $salesByProject = Project::withCount(['bookings as total_sales' => function($query) {
            $query->where('status', 'approved');
        }])->get();

        // 2. Revenue Trend (Last 6 Months) with month-over-month comparison
        $isSqlite = DB::getDriverName() === 'sqlite';
        $dateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        $revenueTrend = Transaction::select(
                DB::raw("{$dateFormat} as month"),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->take(6)
            ->get()
            ->reverse()
            ->values();

        // Calculate growth percentages
        $trendWithGrowth = $revenueTrend->map(function ($item, $index) use ($revenueTrend) {
            $previousTotal = $index > 0 ? $revenueTrend[$index - 1]->total : null;
            $growth = null;
            if ($previousTotal && $previousTotal > 0) {
                $growth = round((($item->total - $previousTotal) / $previousTotal) * 100, 1);
            }
            return [
                'month' => $item->month,
                'total' => $item->total,
                'growth' => $growth,
            ];
        });

        // 3. Lead Conversion by Source (with won count)
        $leadSources = Lead::select(
                'source',
                DB::raw('count(*) as total'),
                DB::raw("SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as won_count")
            )
            ->groupBy('source')
            ->get()
            ->map(function ($source) {
                $source->conversion_rate = $source->total > 0
                    ? round(($source->won_count / $source->total) * 100, 1)
                    : 0;
                return $source;
            });

        // 4. Commission Summary
        $commissions = [
            'paid' => Booking::where('commission_status', 'paid')->sum('commission_amount'),
            'unpaid' => Booking::where('commission_status', 'unpaid')->sum('commission_amount'),
        ];

        return Inertia::render('Reports/Index', [
            'salesByProject' => $salesByProject,
            'revenueTrend' => $trendWithGrowth,
            'leadSources' => $leadSources,
            'commissions' => $commissions,
        ]);
    }
}
