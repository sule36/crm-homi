<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::withCount(['units', 'leads', 'bookings'])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('Projects/Index', [
            'projects' => $projects,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Projects/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:projects',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'address' => 'nullable|string',
            'status' => 'required|in:upcoming,active,completed',
            'amenities' => 'nullable|array',
            'logo' => 'nullable|image|max:5120', // max 5MB
            'master_plan_image' => 'nullable|image|max:20480', // max 20MB
            'brochure_file' => 'nullable|file|max:102400', // max 100MB
        ]);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $request->file('logo')->store('projects/logos', 'public');
        }
        if ($request->hasFile('master_plan_image')) {
            $validated['master_plan_image'] = $request->file('master_plan_image')->store('projects/masterplans', 'public');
        }
        if ($request->hasFile('brochure_file')) {
            $validated['brochure_file'] = $request->file('brochure_file')->store('projects/brochures', 'public');
        }

        $project = Project::create($validated);
        AuditLog::record('created', $project, null, $validated);

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil dibuat.');
    }

    public function show(Project $project)
    {
        $project->load(['unitTypes.units', 'leads' => fn ($q) => $q->latest()->take(10), 'bookings' => fn ($q) => $q->latest()->take(10)]);
        $project->loadCount(['units', 'leads', 'bookings']);

        return Inertia::render('Projects/Show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project)
    {
        return Inertia::render('Projects/Form', [
            'project' => $project,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:projects,code,' . $project->id,
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'address' => 'nullable|string',
            'status' => 'required|in:upcoming,active,completed',
            'amenities' => 'nullable|array',
            'logo' => 'nullable|image|max:5120', // max 5MB
            'master_plan_image' => 'nullable|image|max:20480', // max 20MB
            'brochure_file' => 'nullable|file|max:102400', // max 100MB
        ]);

        $old = $project->toArray();

        if ($request->hasFile('logo')) {
            if ($project->logo) Storage::disk('public')->delete($project->logo);
            $validated['logo'] = $request->file('logo')->store('projects/logos', 'public');
        } else {
            unset($validated['logo']);
        }

        if ($request->hasFile('master_plan_image')) {
            if ($project->master_plan_image) Storage::disk('public')->delete($project->master_plan_image);
            $validated['master_plan_image'] = $request->file('master_plan_image')->store('projects/masterplans', 'public');
        } else {
            unset($validated['master_plan_image']);
        }

        if ($request->hasFile('brochure_file')) {
            if ($project->brochure_file) Storage::disk('public')->delete($project->brochure_file);
            $validated['brochure_file'] = $request->file('brochure_file')->store('projects/brochures', 'public');
        } else {
            unset($validated['brochure_file']);
        }

        $project->update($validated);
        AuditLog::record('updated', $project, $old, $validated);

        return redirect()->route('projects.index')->with('success', 'Proyek berhasil diperbarui.');
    }

    public function destroy(Project $project)
    {
        if ($project->units()->count() > 0) {
            return back()->with('error', 'Proyek tidak dapat dihapus karena masih memiliki unit.');
        }
        if ($project->leads()->count() > 0) {
            return back()->with('error', 'Proyek tidak dapat dihapus karena masih memiliki leads.');
        }
        if ($project->bookings()->count() > 0) {
            return back()->with('error', 'Proyek tidak dapat dihapus karena masih memiliki booking.');
        }

        AuditLog::record('deleted', $project, $project->toArray());
        $project->delete();
        return redirect()->route('projects.index')->with('success', 'Proyek berhasil dihapus.');
    }
}
