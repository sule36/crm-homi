<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Project;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::with('project')->latest()->get();
        
        $campaigns = $campaigns->map(function ($c) {
            $leadsCount = Lead::where('utm_campaign', $c->utm_campaign)->count();
            $conversionsCount = Lead::where('utm_campaign', $c->utm_campaign)->where('status', 'won')->count();
            
            $c->leads_count = $leadsCount;
            $c->conversions_count = $conversionsCount;
            $c->cost_per_lead = $leadsCount > 0 ? $c->budget / $leadsCount : 0;
            $c->cost_per_acquisition = $conversionsCount > 0 ? $c->budget / $conversionsCount : 0;
            return $c;
        });

        return Inertia::render('Campaigns/Index', [
            'campaigns' => $campaigns,
            'projects' => Project::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'platform' => 'required|string',
            'budget' => 'required|numeric|min:0',
            'project_id' => 'nullable|exists:projects,id',
            'utm_campaign' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        Campaign::create($validated);

        return back()->with('success', 'Campaign berhasil ditambahkan.');
    }
}
