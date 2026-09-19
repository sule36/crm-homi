<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class SitePlanController extends Controller
{
    public function index(Request $request, $projectId = null)
    {
        $project = null;
        if ($projectId) {
            $project = Project::with('unitTypes')->find($projectId);
        }
        if (!$project) {
            $project = Project::with('unitTypes')->where('name', 'like', '%Alonica%')->first() 
                ?: Project::with('unitTypes')->first();
        }

        if (!$project) {
            return redirect()->route('projects.index')->with('error', 'Belum ada proyek terdaftar.');
        }

        $allProjects = Project::select('id', 'name', 'code')->get();

        // User role permission check
        $user = Auth::user();
        $isInternal = $user && ($user->hasRole(['super_admin', 'project_manager', 'sales_manager', 'finance', 'admin']) || $user->is_admin);

        // Calculate Real-Time Stock Statistics
        $allProjectUnits = Unit::where('project_id', $project->id)->get();
        $stats = [
            'total' => $allProjectUnits->count(),
            'available' => $allProjectUnits->where('status', 'available')->count(),
            'reserved' => $allProjectUnits->where('status', 'reserved')->count(),
            'booked' => $allProjectUnits->where('status', 'booked')->count(),
            'sold' => $allProjectUnits->where('status', 'sold')->count(),
            'hold' => $allProjectUnits->where('status', 'hold')->count(),
        ];

        // Fetch Units with Filters for Price List & Site Plan Map
        $query = Unit::with([
            'unitType',
            'heldByUser',
            'latestReservation',
            'priceHistories.user',
            'statusHistories.user'
        ])
        ->where('project_id', $project->id)
        ->when($request->search, function ($q, $search) {
            $q->where(function ($sub) use ($search) {
                $sub->where('block', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%")
                    ->orWhere('promo', 'like', "%{$search}%");
            });
        })
        ->when($request->status, fn ($q, $status) => $q->where('status', $status))
        ->when($request->unit_type_id, fn ($q, $typeId) => $q->where('unit_type_id', $typeId))
        ->when($request->block, fn ($q, $block) => $q->where('block', $block))
        ->when($request->min_price, fn ($q, $min) => $q->where('final_price', '>=', $min))
        ->when($request->max_price, fn ($q, $max) => $q->where('final_price', '<=', $max));

        $units = $query->orderBy('block')->orderBy('number')->get();

        // Sanitize internal fields if user is not internal (e.g. Agents)
        if (!$isInternal) {
            $units->transform(function ($u) {
                unset($u->net_price);
                unset($u->commission_notes);
                unset($u->management_notes);
                return $u;
            });
        }

        // Group units by Block for Visual Grid Site Plan
        $unitsByBlock = $units->groupBy('block');

        return Inertia::render('Projects/SitePlan', [
            'project' => $project,
            'projects' => $allProjects,
            'stats' => $stats,
            'units' => $units,
            'unitsByBlock' => $unitsByBlock,
            'filters' => $request->only(['search', 'status', 'unit_type_id', 'block', 'min_price', 'max_price']),
            'isInternal' => $isInternal,
            'unitTypes' => UnitType::where('project_id', $project->id)->get(),
        ]);
    }

    public function updateCoordinates(Request $request, Project $project)
    {
        $validated = $request->validate([
            'siteplan_image' => 'nullable|image|max:10240',
            'coordinates' => 'nullable|array',
            'coordinates.*.unit_id' => 'required|exists:units,id',
            'coordinates.*.x' => 'nullable|numeric',
            'coordinates.*.y' => 'nullable|numeric',
            'coordinates.*.w' => 'nullable|numeric',
            'coordinates.*.h' => 'nullable|numeric',
        ]);

        if ($request->hasFile('siteplan_image')) {
            if ($project->siteplan_image) {
                Storage::disk('public')->delete($project->siteplan_image);
            }
            $path = $request->file('siteplan_image')->store('projects/siteplans', 'public');
            $project->update(['siteplan_image' => $path]);
        }

        if (!empty($validated['coordinates'])) {
            foreach ($validated['coordinates'] as $item) {
                Unit::where('id', $item['unit_id'])->update([
                    'siteplan_coordinates' => [
                        'x' => $item['x'] ?? 0,
                        'y' => $item['y'] ?? 0,
                        'w' => $item['w'] ?? 5,
                        'h' => $item['h'] ?? 5,
                    ]
                ]);
            }
        }

        AuditLog::record('updated_siteplan', $project, null, ['coordinates_count' => count($validated['coordinates'] ?? [])]);

        return back()->with('success', 'Layout Site Plan berhasil disimpan.');
    }

    public function exportExcel(Request $request, Project $project)
    {
        $units = Unit::with('unitType')
            ->where('project_id', $project->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->unit_type_id, fn ($q, $t) => $q->where('unit_type_id', $t))
            ->orderBy('block')->orderBy('number')
            ->get();

        $filename = "PriceList_" . str_replace(' ', '_', $project->name) . "_" . date('Y-m-d') . ".csv";

        $headers = [
            "Content-type" => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename={$filename}",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ];

        $callback = function () use ($units, $project) {
            $file = fopen('php://output', 'w');
            // Write BOM for UTF-8 Excel support
            fputs($file, "\xEF\xBB\xBF");

            fputcsv($file, ["PRICE LIST REGULAR - " . strtoupper($project->name)]);
            fputcsv($file, ["Tanggal Cetak: " . date('d-m-Y H:i')]);
            fputcsv($file, []);
            fputcsv($file, ['No', 'Blok & No', 'Tipe Unit', 'LT (m2)', 'LB (m2)', 'Kamar', 'Carport', 'Arah Hadap', 'Harga Jual (Rp)', 'Promo / Catatan', 'Status']);

            $no = 1;
            foreach ($units as $u) {
                fputcsv($file, [
                    $no++,
                    $u->label,
                    $u->unitType?->name ?? '-',
                    $u->unitType?->land_area ?? '-',
                    $u->unitType?->building_area ?? '-',
                    ($u->unitType?->bedrooms ?? 0) . ' KT / ' . ($u->unitType?->bathrooms ?? 0) . ' KM',
                    $u->carport . ' Carport',
                    $u->facing_direction ?? '-',
                    number_format($u->final_price, 0, ',', '.'),
                    $u->promo ?: '-',
                    strtoupper($u->status)
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportPdf(Request $request, Project $project)
    {
        $mode = $request->input('mode', 'combined'); // siteplan | pricelist | combined
        $version = $request->input('version', 'customer'); // customer | agent | internal

        // Check if user is allowed to download internal version
        $user = Auth::user();
        $isInternalUser = $user && ($user->hasRole(['super_admin', 'project_manager', 'sales_manager', 'finance', 'admin']) || $user->is_admin);
        if ($version === 'internal' && !$isInternalUser) {
            $version = 'agent';
        }

        // Fetch filtered units
        $query = Unit::with(['unitType', 'heldByUser'])
            ->where('project_id', $project->id)
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->unit_type_id, fn ($q, $t) => $q->where('unit_type_id', $t))
            ->when($request->block, fn ($q, $b) => $q->where('block', $b))
            ->when($request->min_price, fn ($q, $min) => $q->where('final_price', '>=', $min))
            ->when($request->max_price, fn ($q, $max) => $q->where('final_price', '<=', $max));

        $units = $query->orderBy('block')->orderBy('number')->get();
        $unitsByBlock = $units->groupBy('block');

        // Stats
        $allUnits = Unit::where('project_id', $project->id)->get();
        $stats = [
            'total' => $allUnits->count(),
            'available' => $allUnits->where('status', 'available')->count(),
            'reserved' => $allUnits->where('status', 'reserved')->count(),
            'booked' => $allUnits->where('status', 'booked')->count(),
            'sold' => $allUnits->where('status', 'sold')->count(),
            'hold' => $allUnits->where('status', 'hold')->count(),
        ];

        $printedAt = date('d F Y, H:i') . ' WIB';
        $docCode = 'DOC-' . strtoupper($project->code ?: 'PRJ') . '-' . date('Ymd-Hi');

        $title = "Site Plan & Price List " . $project->name;

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.siteplan_pricelist', compact(
            'project', 'units', 'unitsByBlock', 'stats', 'mode', 'version', 'printedAt', 'docCode', 'title'
        ))->setPaper('a4', 'landscape');

        $filename = "SitePlan_PriceList_" . str_replace(' ', '_', $project->name) . "_" . date('Ymd_His') . ".pdf";

        return $pdf->stream($filename);
    }

    public function updatePdfSettings(Request $request, Project $project)
    {
        $validated = $request->validate([
            'valid_until' => 'nullable|string',
            'bank_info' => 'nullable|string',
            'notes' => 'nullable|string',
            'order_steps' => 'nullable|string',
            'partner_banks' => 'nullable|array',
        ]);

        $settings = $project->settings ?? [];
        $settings['pricelist_valid_until'] = $validated['valid_until'] ?? null;
        $settings['pricelist_bank_info'] = $validated['bank_info'] ?? null;
        $settings['pricelist_notes'] = $validated['notes'] ?? null;
        $settings['pricelist_order_steps'] = $validated['order_steps'] ?? null;
        $settings['pricelist_partner_banks'] = $validated['partner_banks'] ?? [];

        $project->update(['settings' => $settings]);
        AuditLog::record('updated_pricelist_settings', $project, null, $settings);

        return back()->with('success', 'Pengaturan Keterangan & Bank Price List PDF berhasil diperbarui.');
    }
}
