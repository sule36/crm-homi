<?php

namespace App\Http\Controllers;

use App\Models\SalesTarget;
use App\Models\User;
use App\Models\Booking;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class KPIController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?? now()->format('Y-m');
        
        $agents = User::role('sales_agent')->get();

        $isSqlite = DB::getDriverName() === 'sqlite';
        $bookingDateFormat = $isSqlite ? "strftime('%Y-%m', booking_date)" : "DATE_FORMAT(booking_date, '%Y-%m')";
        $leadDateFormat = $isSqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";

        // Bulk fetch all booking stats for the month (ONE query)
        $bookingStats = Booking::where('status', 'approved')
            ->where(DB::raw($bookingDateFormat), $month)
            ->selectRaw('booked_by, COUNT(*) as units, COALESCE(SUM(base_price), SUM(final_price)) as revenue')
            ->groupBy('booked_by')
            ->get()
            ->keyBy('booked_by');

        // Bulk fetch all lead counts for the month (ONE query)
        $leadCounts = Lead::where(DB::raw($leadDateFormat), $month)
            ->selectRaw('assigned_to, COUNT(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        // Bulk fetch all targets for the month (ONE query)
        $targets = SalesTarget::where('month', $month)->get()->keyBy('user_id');

        $kpiData = $agents->map(function ($agent) use ($bookingStats, $leadCounts, $targets) {
            $stats = $bookingStats[$agent->id] ?? null;
            $target = $targets[$agent->id] ?? null;
            $achievedUnits = $stats->units ?? 0;

            return [
                'agent_id' => $agent->id,
                'agent_name' => $agent->name,
                'target' => $target,
                'achieved' => [
                    'units' => $achievedUnits,
                    'revenue' => (float) ($stats->revenue ?? 0),
                    'leads' => $leadCounts[$agent->id] ?? 0,
                ],
                'percentage' => $target && $target->target_units > 0 
                    ? round(($achievedUnits / $target->target_units) * 100) 
                    : 0
            ];
        });

        return Inertia::render('KPI/Index', [
            'kpiData' => $kpiData,
            'currentMonth' => $month,
        ]);
    }

    public function setTarget(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'month' => 'required',
            'target_units' => 'required|integer|min:0',
            'target_revenue' => 'required|numeric|min:0',
        ]);

        SalesTarget::updateOrCreate(
            ['user_id' => $validated['user_id'], 'month' => $validated['month']],
            ['target_units' => $validated['target_units'], 'target_revenue' => $validated['target_revenue']]
        );

        return back()->with('success', 'Target berhasil diatur.');
    }
}
