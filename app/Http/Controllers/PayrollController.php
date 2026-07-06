<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use App\Models\PayrollDeduction;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->month ?: now()->month;
        $year = $request->year ?: now()->year;

        $payrolls = Payroll::with(['user', 'deductions'])
            ->where('period_month', $month)
            ->where('period_year', $year)
            ->orderBy('user_id')
            ->get();

        $totalDraft = $payrolls->where('status', 'draft')->sum('net_salary');
        $totalPaid = $payrolls->where('status', 'paid')->sum('net_salary');

        // Employee salary structures
        $salaryStructures = EmployeeSalary::with('user')
            ->latest('effective_date')
            ->get()
            ->unique('user_id');

        return Inertia::render('Finance/Payroll', [
            'payrolls' => $payrolls,
            'salary_structures' => $salaryStructures->values(),
            'users' => User::orderBy('name')->get(['id', 'name', 'email']),
            'stats' => [
                'total_draft' => (int) $totalDraft,
                'total_paid' => (int) $totalPaid,
                'total_payrolls' => $payrolls->count(),
                'paid_count' => $payrolls->where('status', 'paid')->count(),
            ],
            'filters' => [
                'month' => (int) $month,
                'year' => (int) $year,
            ],
        ]);
    }

    /**
     * Generate payrolls for all employees with salary structures for a given period.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|between:1,12',
            'year' => 'required|integer|min:2020',
        ]);

        $month = $request->month;
        $year = $request->year;

        // Get all users with active salary structures
        $salaryStructures = EmployeeSalary::with('user')
            ->latest('effective_date')
            ->get()
            ->unique('user_id');

        $generated = 0;

        DB::transaction(function () use ($salaryStructures, $month, $year, &$generated) {
            foreach ($salaryStructures as $salary) {
                // Skip if payroll already exists for this period
                $exists = Payroll::where('user_id', $salary->user_id)
                    ->where('period_month', $month)
                    ->where('period_year', $year)
                    ->exists();

                if ($exists) continue;

                $totalAllowances = $salary->transport_allowance + $salary->meal_allowance
                    + $salary->position_allowance + $salary->other_allowance;

                $netSalary = $salary->basic_salary + $totalAllowances;

                Payroll::create([
                    'user_id' => $salary->user_id,
                    'period_month' => $month,
                    'period_year' => $year,
                    'basic_salary' => $salary->basic_salary,
                    'total_allowances' => $totalAllowances,
                    'total_deductions' => 0,
                    'bonus' => 0,
                    'overtime' => 0,
                    'net_salary' => $netSalary,
                    'status' => 'draft',
                ]);

                $generated++;
            }
        });

        if ($generated === 0) {
            return back()->with('error', 'Tidak ada gaji baru yang bisa di-generate. Pastikan sudah ada struktur gaji karyawan atau belum di-generate untuk periode ini.');
        }

        return back()->with('success', "Berhasil generate {$generated} slip gaji untuk periode ini.");
    }

    /**
     * Pay a single payroll.
     */
    public function pay(Request $request, Payroll $payroll)
    {
        if ($payroll->status === 'paid') {
            return back()->with('error', 'Gaji ini sudah dibayarkan.');
        }

        $payroll->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_by' => auth()->id(),
            'payment_method' => $request->payment_method ?? 'transfer',
        ]);

        AuditLog::record('payroll_paid', $payroll, null, $payroll->toArray());

        return back()->with('success', "Gaji {$payroll->user->name} berhasil dibayarkan.");
    }

    /**
     * Pay all draft payrolls for a period.
     */
    public function payAll(Request $request)
    {
        $request->validate([
            'month' => 'required|integer',
            'year' => 'required|integer',
        ]);

        $count = 0;

        DB::transaction(function () use ($request, &$count) {
            $drafts = Payroll::where('period_month', $request->month)
                ->where('period_year', $request->year)
                ->where('status', 'draft')
                ->get();

            foreach ($drafts as $payroll) {
                $payroll->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'paid_by' => auth()->id(),
                    'payment_method' => 'transfer',
                ]);
                $count++;
            }
        });

        return back()->with('success', "Berhasil membayar {$count} gaji karyawan.");
    }

    /**
     * View payroll slip detail.
     */
    public function slip(Payroll $payroll)
    {
        $payroll->load(['user', 'deductions', 'paidByUser']);

        return Inertia::render('Finance/PayrollSlip', [
            'payroll' => $payroll,
        ]);
    }

    /**
     * Store a new employee salary structure.
     */
    public function storeSalary(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'basic_salary' => 'required|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'position_allowance' => 'nullable|numeric|min:0',
            'other_allowance' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $salary = EmployeeSalary::create($validated);

        AuditLog::record('salary_structure_created', $salary, null, $salary->toArray());

        return back()->with('success', 'Struktur gaji berhasil disimpan.');
    }

    /**
     * Update an existing salary structure.
     */
    public function updateSalary(Request $request, EmployeeSalary $employeeSalary)
    {
        $validated = $request->validate([
            'basic_salary' => 'required|numeric|min:0',
            'transport_allowance' => 'nullable|numeric|min:0',
            'meal_allowance' => 'nullable|numeric|min:0',
            'position_allowance' => 'nullable|numeric|min:0',
            'other_allowance' => 'nullable|numeric|min:0',
            'effective_date' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $old = $employeeSalary->toArray();
        $employeeSalary->update($validated);

        AuditLog::record('salary_structure_updated', $employeeSalary, $old, $employeeSalary->fresh()->toArray());

        return back()->with('success', 'Struktur gaji berhasil diperbarui.');
    }
}
