<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\ProjectDutySchedule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class DutyScheduleController extends Controller
{
    /**
     * Set the In-House Duty Agent for today (or specified date).
     */
    public function setTodayDuty(Request $request)
    {
        $validated = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'user_id' => 'required|exists:users,id',
            'duty_date' => 'nullable|date',
            'shift' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $date = $validated['duty_date'] ?? now()->toDateString();
        $shift = $validated['shift'] ?? 'full_day';
        $companyId = auth()->user()?->company_id ?? 1;

        $user = User::findOrFail($validated['user_id']);

        // Record in duty schedule table
        $schedule = ProjectDutySchedule::updateOrCreate(
            [
                'project_id' => $validated['project_id'],
                'duty_date' => $date,
                'shift' => $shift,
            ],
            [
                'company_id' => $companyId,
                'user_id' => $user->id,
                'status' => 'active',
                'notes' => $validated['notes'] ?? 'Ditetapkan via Quick Duty Scheduler',
            ]
        );

        // Also save persistent setting for instant lookup
        Setting::set("duty_agent_{$validated['project_id']}", $user->id);

        $project = Project::find($validated['project_id']);
        $projectName = $project?->name ?? 'Proyek';

        return back()->with('success', "Petugas Jaga Harian ({$projectName}) berhasil ditetapkan: {$user->name}. Tamu Walk-In akan otomatis diarahkan ke beliau.");
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

        // If no duty agent set specifically, pick requested user or first active inhouse agent
        if (!$dutyAgent && $request->filled('user_id')) {
            $dutyAgent = User::find($request->user_id);
        }

        if (!$dutyAgent) {
            $dutyAgent = User::role('sales_agent')
                ->where('status', 'active')
                ->where('agent_type', 'inhouse')
                ->when($projectId, fn($q) => $q->where('project_id', $projectId))
                ->first();
        }

        if (!$dutyAgent) {
            return back()->with('error', 'Belum ada Petugas Jaga yang ditetapkan untuk proyek ini. Silakan tentukan Petugas Jaga terlebih dahulu.');
        }

        $oldAgentName = $lead->assignedTo?->name ?? 'Belum Ditugaskan';

        // If it's an external broker lead, set duty agent as inhouse_pic_id
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
