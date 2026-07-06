<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadCaptureController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'project_id' => 'nullable|exists:projects,id',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Logic to assign lead to project if not provided
        $projectId = $request->project_id ?? Project::first()?->id;

        $lead = Lead::create([
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'project_id' => $projectId,
            'source' => $request->utm_source ?? 'website',
            'utm_campaign' => $request->utm_campaign,
            'notes' => $request->notes,
            'status' => 'new',
        ]);

        // Trigger score calculation & Auto-assignment
        $lead->recalculateScore();
        \App\Services\LeadAssignmentService::assign($lead);

        return response()->json([
            'status' => 'success',
            'message' => 'Lead captured successfully',
            'data' => $lead
        ], 201);
    }
}
