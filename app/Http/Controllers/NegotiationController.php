<?php

namespace App\Http\Controllers;

use App\Models\Negotiation;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Reservation;
use App\Models\Unit;
use App\Models\Project;
use App\Models\User;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NegotiationController extends Controller
{
    private function getNegotiationSettingsPayload(): array
    {
        $sigConfig = Setting::getSignatureConfig();

        return [
            'company_name' => Setting::get('company_name') ?: null,
            'company_logo' => Setting::get('company_logo') ?: null,
            'standard_booking_fee' => Setting::getStandardBookingFee(),
            'spr_default_free_ppn' => (bool) Setting::get('spr_default_free_ppn', true),
            'spr_default_free_legal' => (bool) Setting::get('spr_default_free_legal', true),
            'city' => $sigConfig['city'] ?? 'Jakarta Selatan',
            'sig1_name' => $sigConfig['sig1_name'] ?? '',
            'sig1_title' => $sigConfig['sig1_title'] ?? 'Sales Manager',
            'sig1_image' => $sigConfig['sig1_image'] ?? null,
            'sig2_name' => $sigConfig['sig2_name'] ?? '',
            'sig2_title' => $sigConfig['sig2_title'] ?? 'Direktur',
            'sig2_image' => $sigConfig['sig2_image'] ?? null,
        ];
    }

    private function unitIsUnavailableForNewDeal(Unit $unit, ?int $allowUnitId = null): bool
    {
        if ($allowUnitId && (int)$unit->id === $allowUnitId) {
            return false;
        }

        $status = strtolower(trim((string) $unit->status));
        if (in_array($status, ['sold', 'booked'], true)) {
            return true;
        }

        return Booking::withoutGlobalScopes()
            ->where('unit_id', $unit->id)
            ->whereNull('deleted_at')
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('approved', 'completed', 'booked')")
            ->exists();
    }

    private function parseDigitsToInt($value): ?int
    {
        if ($value === null || $value === '' || $value === '-') {
            return null;
        }
        $cleaned = preg_replace('/[^0-9]/', '', (string) $value);
        if ($cleaned === '') {
            return null;
        }
        return (int) $cleaned;
    }

    private function parseSchemeFromLabel(?string $label): string
    {
        $cb = strtolower((string) $label);
        if (str_contains($cb, 'keras')) {
            return 'cash_keras';
        }
        if (str_contains($cb, 'kpr')) {
            return 'kpr';
        }
        return 'cash_bertahap';
    }

    private function parseBonusItemsFromString(?string $bonusText): array
    {
        $raw = trim((string) $bonusText, "() \t\n\r\0\x0B");
        if ($raw === '' || $raw === '-') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    private function buildSyncedFormData(
        array $baseFormData,
        ?int $offeredPrice,
        string $paymentScheme,
        ?int $dpAmount,
        ?int $installmentMonths,
        array $bonusItems,
        ?int $counterPrice = null,
        ?string $clientName = null
    ): array {
        $formData = $baseFormData;
        if (!isset($formData['pengajuan']) || !is_array($formData['pengajuan'])) {
            $formData['pengajuan'] = [];
        }
        if (!isset($formData['jawaban']) || !is_array($formData['jawaban'])) {
            $formData['jawaban'] = [];
        }

        if ($clientName) {
            $formData['dari'] = $clientName;
            $formData['jawaban']['sig_pengaju_name'] = $clientName;
        }

        if (empty($formData['pengajuan']['cara_bayar'])) {
            $formData['pengajuan']['cara_bayar'] = Negotiation::formatSchemeLabel($paymentScheme, $installmentMonths);
        }
        if ($offeredPrice !== null && $offeredPrice > 0) {
            $formData['pengajuan']['price'] = Negotiation::formatNominal($offeredPrice);
        }
        if ($dpAmount !== null && $dpAmount > 0 && empty($formData['pengajuan']['dp1_amount'])) {
            $formData['pengajuan']['dp1_amount'] = Negotiation::formatNominal($dpAmount);
        }
        if (!empty($bonusItems) && empty($formData['bonus'])) {
            $formData['bonus'] = '(' . implode(', ', $bonusItems) . ')';
        }

        if ($counterPrice !== null && $counterPrice > 0) {
            $formData['jawaban']['price'] = Negotiation::formatNominal($counterPrice);
        }

        return $formData;
    }

    /**
     * CRM Internal: List all negotiations
     */
    public function index(Request $request)
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('negotiations')) {
            return Inertia::render('Negotiations/Index', [
                'negotiations' => ['data' => [], 'total' => 0, 'from' => 0, 'to' => 0, 'last_page' => 1, 'links' => []],
                'stats' => ['total' => 0, 'pending' => 0, 'counter_offer' => 0, 'approved' => 0, 'rejected' => 0, 'converted' => 0],
                'filters' => $request->only(['status', 'project_id', 'created_by', 'search']),
                'projects' => Project::select('id', 'name')->get(),
                'agents' => [],
                'units' => [],
                'leads' => [],
                'standardBookingFee' => Setting::getStandardBookingFee(),
                'negotiationSettings' => $this->getNegotiationSettingsPayload(),
            ]);
        }

        $user = auth()->user();

        $query = Negotiation::with(['lead', 'unit.unitType', 'unit.project', 'project', 'creator', 'reviewer', 'booking'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->project_id, fn ($q, $p) => $q->where('project_id', $p))
            ->when($request->created_by, fn ($q, $a) => $q->where('created_by', $a))
            ->when($request->search, fn ($q, $s) => $q->where(function ($sub) use ($s) {
                $sub->where('client_name', 'like', "%{$s}%")
                    ->orWhere('client_phone', 'like', "%{$s}%");
            }));

        // Role-based visibility scoping for Agent & Master Lead monitoring
        $isAdmin = $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('developer');
        $isMasterLead = $user->hasRole('master_lead') || $user->agent_type === 'master_lead';

        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $query->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $query->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        // Auto-expire old negotiations
        Negotiation::where('status', 'draft')
            ->whereNotNull('expired_at')
            ->where('expired_at', '<', now())
            ->update(['status' => 'expired']);

        $negotiations = $query->latest()->paginate(20)->withQueryString();
        $negotiations->getCollection()->transform(function ($n) {
            $n->form_details = $n->getFormDetails();
            return $n;
        });

        // Stats scoping
        $statsBase = Negotiation::query();
        if (!$isAdmin) {
            if ($isMasterLead) {
                $teamUserIds = $teamUserIds ?? User::where('master_lead_id', $user->id)->pluck('id')->push($user->id);
                $statsBase->where(function ($q) use ($teamUserIds) {
                    $q->whereIn('created_by', $teamUserIds)
                      ->orWhereHas('lead', fn ($l) => $l->whereIn('assigned_to', $teamUserIds));
                });
            } else {
                $statsBase->where(function ($q) use ($user) {
                    $q->where('created_by', $user->id)
                      ->orWhereHas('lead', fn ($l) => $l->where('assigned_to', $user->id));
                });
            }
        }

        $stats = [
            'total' => (clone $statsBase)->count(),
            'pending' => (clone $statsBase)->where('status', 'pending')->count(),
            'counter_offer' => (clone $statsBase)->where('status', 'counter_offer')->count(),
            'approved' => (clone $statsBase)->where('status', 'approved')->count(),
            'rejected' => (clone $statsBase)->where('status', 'rejected')->count(),
            'converted' => (clone $statsBase)->whereNotNull('booking_id')->count(),
        ];

        $units = Unit::selectableForLov()
            ->select('id', 'block', 'number', 'floor', 'final_price', 'discount_amount', 'promo', 'premium_charge', 'net_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name,address,code,logo', 'unitType:id,name,land_area,building_area,current_price'])
            ->orderByUnitNumber()
            ->get();

        $leads = Lead::select('id', 'name', 'phone', 'email', 'project_id')
            ->with(['reservations' => fn ($q) => $q->where('status', 'active')->latest()])
            ->orderBy('name')
            ->get();

        return Inertia::render('Negotiations/Index', [
            'negotiations' => $negotiations,
            'stats' => $stats,
            'filters' => $request->only(['status', 'project_id', 'created_by', 'search']),
            'projects' => Project::select('id', 'name')->get(),
            'agents' => User::select('id', 'name')->where(function ($q) {
                $q->whereIn('agent_type', ['inhouse', 'freelance', 'master_lead'])
                  ->orWhereHas('roles', fn ($rq) => $rq->whereIn('name', ['sales_agent', 'master_lead', 'broker']));
            })->get(),
            'units' => $units,
            'leads' => $leads,
            'standardBookingFee' => Setting::getStandardBookingFee(),
            'negotiationSettings' => $this->getNegotiationSettingsPayload(),
        ]);
    }

    /**
     * CRM Internal: Create a new negotiation (generate link)
     */
    public function store(Request $request)
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'nullable|exists:leads,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'developer_sig_name' => 'nullable|string|max:255',
            'developer_sig_title' => 'nullable|string|max:255',
            'special_bonus_items' => 'nullable|array',
            'form_data' => 'nullable|array',
            'offered_price' => 'nullable|numeric|min:0',
            'counter_price' => 'nullable|numeric|min:0',
            'payment_scheme' => 'nullable|string',
            'dp_amount' => 'nullable|numeric|min:0',
            'installment_months' => 'nullable|integer|min:0|max:360',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:draft,pending,counter_offer,approved,rejected,expired',
        ]);

        $unit = Unit::with(['project', 'unitType'])->findOrFail($request->unit_id);
        if ($this->unitIsUnavailableForNewDeal($unit)) {
            return back()->withErrors(['unit_id' => "Unit {$unit->label} sudah berstatus {$unit->status} / memiliki booking aktif dan tidak dapat dipilih untuk negosiasi baru."])->withInput();
        }

        $settings = $this->getSettings();
        $defaultSigName = $settings['spr_signatures']['sig2_name'] ?? $settings['spr_signatures']['sig1_name'] ?? 'Direktur';
        $defaultSigTitle = $settings['spr_signatures']['sig2_title'] ?? $settings['spr_signatures']['sig1_title'] ?? 'Developer Representative';

        $inputFormData = is_array($request->form_data) ? $request->form_data : [];

        $offeredPrice = $request->offered_price !== null && $request->offered_price !== ''
            ? (int)$request->offered_price
            : $this->parseDigitsToInt($inputFormData['pengajuan']['price'] ?? null);

        $counterPrice = $request->counter_price !== null && $request->counter_price !== ''
            ? (int)$request->counter_price
            : $this->parseDigitsToInt($inputFormData['jawaban']['price'] ?? null);

        $paymentScheme = $request->payment_scheme;
        if (empty($paymentScheme) && !empty($inputFormData['pengajuan']['cara_bayar'])) {
            $paymentScheme = $this->parseSchemeFromLabel($inputFormData['pengajuan']['cara_bayar']);
        }
        $paymentScheme = $paymentScheme ?: 'cash_bertahap';

        $installmentMonths = $request->installment_months;
        if (empty($installmentMonths) && !empty($inputFormData['pengajuan']['cara_bayar'])) {
            if (preg_match('/(\d+)\s*[xX]/', $inputFormData['pengajuan']['cara_bayar'], $m)) {
                $installmentMonths = (int)$m[1];
            }
        }

        $dpAmount = $request->dp_amount !== null && $request->dp_amount !== ''
            ? (int)$request->dp_amount
            : $this->parseDigitsToInt($inputFormData['pengajuan']['dp1_amount'] ?? null);

        $bonusItems = is_array($request->special_bonus_items) && count($request->special_bonus_items) > 0
            ? array_values(array_filter(array_map('trim', $request->special_bonus_items)))
            : $this->parseBonusItemsFromString($inputFormData['bonus'] ?? null);

        $syncedFormData = !empty($inputFormData)
            ? $this->buildSyncedFormData(
                $inputFormData,
                $offeredPrice,
                $paymentScheme,
                $dpAmount,
                $installmentMonths ? (int)$installmentMonths : null,
                $bonusItems,
                $counterPrice,
                $request->client_name
            )
            : null;

        $sigName = $request->developer_sig_name
            ?: ($inputFormData['jawaban']['sig_menyetujui_name'] ?? null)
            ?: $defaultSigName;

        $negotiation = Negotiation::create([
            'unit_id' => $unit->id,
            'project_id' => $unit->project_id,
            'lead_id' => $request->lead_id,
            'created_by' => auth()->id(),
            'client_name' => $request->client_name,
            'client_phone' => $request->client_phone,
            'client_email' => $request->client_email,
            'unit_listed_price' => $unit->display_price ?: ($unit->final_price ?? 0),
            'offered_price' => $offeredPrice ?: null,
            'counter_price' => $counterPrice ?: null,
            'payment_scheme' => $paymentScheme,
            'dp_amount' => $dpAmount ?: null,
            'installment_months' => $installmentMonths ? (int)$installmentMonths : null,
            'notes' => $request->notes ?: null,
            'form_data' => $syncedFormData,
            'developer_sig_name' => $sigName,
            'developer_sig_title' => $request->developer_sig_title ?: $defaultSigTitle,
            'special_bonus_items' => $bonusItems,
            'status' => $request->status ?: 'draft',
        ]);

        // Log activity if linked to a lead
        if ($negotiation->lead_id) {
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "📋 Form Negosiasi dibuat untuk unit {$unit->code}. Link: {$negotiation->getPublicUrl()}",
            ]);
        }

        return back()
            ->with('negotiation_id', $negotiation->id)
            ->with('negotiation_link', $negotiation->getPublicUrl())
            ->with('negotiation_token', $negotiation->token)
            ->with('success', 'Form negosiasi berhasil dibuat & disesuaikan!');
    }

    /**
     * CRM Internal: Show negotiation detail
     */
    public function show(Negotiation $negotiation)
    {
        $negotiation->load(['lead.reservations' => fn ($q) => $q->where('status', 'active')->latest(), 'unit.unitType', 'unit.project', 'project', 'creator', 'reviewer', 'booking']);
        $negotiation->form_details = $negotiation->getFormDetails();

        $units = Unit::selectableForLov($negotiation->unit_id)
            ->when($negotiation->project_id, fn ($q) => $q->where('project_id', $negotiation->project_id))
            ->select('id', 'block', 'number', 'floor', 'final_price', 'discount_amount', 'promo', 'premium_charge', 'net_price', 'project_id', 'unit_type_id', 'status')
            ->with(['project:id,name,address,code,logo', 'unitType:id,name,land_area,building_area,current_price'])
            ->orderByUnitNumber()
            ->get();

        $leads = Lead::select('id', 'name', 'phone', 'email', 'project_id')
            ->with(['reservations' => fn ($q) => $q->where('status', 'active')->latest()])
            ->orderBy('name')
            ->get();

        return Inertia::render('Negotiations/Show', [
            'negotiation' => $negotiation,
            'units' => $units,
            'leads' => $leads,
            'standardBookingFee' => Setting::getStandardBookingFee(),
            'negotiationSettings' => $this->getNegotiationSettingsPayload(),
        ]);
    }

    /**
     * CRM Internal: Update negotiation details (client info, unit, price, terms)
     */
    public function update(Request $request, Negotiation $negotiation)
    {
        $validated = $request->validate([
            'unit_id' => 'required|exists:units,id',
            'lead_id' => 'nullable|exists:leads,id',
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'offered_price' => 'nullable|numeric|min:0',
            'payment_scheme' => 'nullable|string|in:cash_keras,cash_bertahap,kpr',
            'dp_amount' => 'nullable|numeric|min:0',
            'installment_months' => 'nullable|integer|min:0|max:360',
            'notes' => 'nullable|string|max:2000',
            'status' => 'nullable|string|in:draft,pending,counter_offer,approved,rejected,expired',
            'developer_sig_name' => 'nullable|string|max:255',
            'developer_sig_title' => 'nullable|string|max:255',
            'counter_price' => 'nullable|numeric|min:0',
            'counter_notes' => 'nullable|string|max:1000',
            'special_bonus_items' => 'nullable|array',
            'form_data' => 'nullable|array',
        ]);

        $oldUnitId = $negotiation->unit_id;
        $newUnit = Unit::with(['project', 'unitType'])->findOrFail($validated['unit_id']);
        if ($this->unitIsUnavailableForNewDeal($newUnit, (int)$oldUnitId)) {
            return back()->withErrors(['unit_id' => "Unit {$newUnit->label} sudah berstatus {$newUnit->status} / memiliki booking aktif dan tidak dapat dipilih."])->withInput();
        }

        $updateData = [
            'lead_id' => array_key_exists('lead_id', $validated) ? ($validated['lead_id'] ?: null) : $negotiation->lead_id,
            'client_name' => $validated['client_name'],
            'client_phone' => $validated['client_phone'],
            'client_email' => $validated['client_email'] ?? null,
            'unit_id' => $newUnit->id,
            'project_id' => $newUnit->project_id,
            'unit_listed_price' => $newUnit->display_price ?: ($newUnit->final_price ?? $negotiation->unit_listed_price),
            'offered_price' => $validated['offered_price'] ?? $negotiation->offered_price,
            'payment_scheme' => $validated['payment_scheme'] ?? $negotiation->payment_scheme,
            'dp_amount' => $validated['dp_amount'] ?? $negotiation->dp_amount,
            'installment_months' => $validated['installment_months'] ?? $negotiation->installment_months,
            'notes' => $validated['notes'] ?? $negotiation->notes,
            'developer_sig_name' => $validated['developer_sig_name'] ?? $negotiation->developer_sig_name,
            'developer_sig_title' => $validated['developer_sig_title'] ?? $negotiation->developer_sig_title,
        ];

        $bonusItems = array_key_exists('special_bonus_items', $validated)
            ? array_values(array_filter(array_map('trim', $validated['special_bonus_items'] ?? [])))
            : ($negotiation->special_bonus_items ?? []);

        if (array_key_exists('form_data', $validated) && is_array($validated['form_data'])) {
            $fd = $validated['form_data'];
            $parsedOffer = $this->parseDigitsToInt($fd['pengajuan']['price'] ?? null);
            if ($parsedOffer !== null) {
                $updateData['offered_price'] = $parsedOffer;
            }
            $parsedCounter = $this->parseDigitsToInt($fd['jawaban']['price'] ?? null);
            if ($parsedCounter !== null) {
                $updateData['counter_price'] = $parsedCounter;
            }
            if (!empty($fd['pengajuan']['cara_bayar'])) {
                if (empty($validated['payment_scheme'])) {
                    $updateData['payment_scheme'] = $this->parseSchemeFromLabel($fd['pengajuan']['cara_bayar']);
                }
                if (!isset($validated['installment_months']) && preg_match('/(\d+)\s*(?:[xX]|bulan|bln)/i', $fd['pengajuan']['cara_bayar'], $m)) {
                    $updateData['installment_months'] = (int)$m[1];
                }
            }
            $parsedDp = $this->parseDigitsToInt($fd['pengajuan']['dp1_amount'] ?? null);
            if ($parsedDp !== null && !isset($validated['dp_amount'])) {
                $updateData['dp_amount'] = $parsedDp;
            }
            if (!array_key_exists('special_bonus_items', $validated) && !empty($fd['bonus'])) {
                $bonusItems = $this->parseBonusItemsFromString($fd['bonus']);
            }
            if (!empty($fd['jawaban']['sig_menyetujui_name']) && empty($validated['developer_sig_name'])) {
                $updateData['developer_sig_name'] = $fd['jawaban']['sig_menyetujui_name'];
            }

            $updateData['form_data'] = $this->buildSyncedFormData(
                $fd,
                $updateData['offered_price'] ? (int)$updateData['offered_price'] : null,
                $updateData['payment_scheme'] ?: 'cash_bertahap',
                $updateData['dp_amount'] ? (int)$updateData['dp_amount'] : null,
                $updateData['installment_months'] ? (int)$updateData['installment_months'] : null,
                $bonusItems,
                isset($updateData['counter_price']) ? (int)$updateData['counter_price'] : ($negotiation->counter_price ? (int)$negotiation->counter_price : null),
                $updateData['client_name']
            );
        } elseif (is_array($negotiation->form_data)) {
            $updateData['form_data'] = $this->buildSyncedFormData(
                $negotiation->form_data,
                $updateData['offered_price'] ? (int)$updateData['offered_price'] : null,
                $updateData['payment_scheme'] ?: 'cash_bertahap',
                $updateData['dp_amount'] ? (int)$updateData['dp_amount'] : null,
                $updateData['installment_months'] ? (int)$updateData['installment_months'] : null,
                $bonusItems,
                array_key_exists('counter_price', $validated) ? ($validated['counter_price'] ? (int)$validated['counter_price'] : null) : ($negotiation->counter_price ? (int)$negotiation->counter_price : null),
                $updateData['client_name']
            );
        }

        $updateData['special_bonus_items'] = $bonusItems;

        if (!empty($validated['status'])) {
            $updateData['status'] = $validated['status'];
            if ($validated['status'] === 'approved') {
                $updateData['reviewed_by'] = auth()->id();
                $updateData['reviewed_at'] = now();
            }
        }
        if (array_key_exists('counter_price', $validated)) {
            $updateData['counter_price'] = $validated['counter_price'];
        }
        if (array_key_exists('counter_notes', $validated)) {
            $updateData['counter_notes'] = $validated['counter_notes'];
        }

        $negotiation->update($updateData);

        // If approved and linked to lead, log activity and update lead status
        if (($updateData['status'] ?? '') === 'approved' && $negotiation->lead_id) {
            Lead::where('id', $negotiation->lead_id)->update(['status' => 'negotiation']);
            $dealPrice = $updateData['counter_price'] ?? $updateData['offered_price'] ?? $negotiation->offered_price;
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'status_change',
                'description' => "✅ Jawaban Developer diisi & Negosiasi DISETUJUI untuk unit {$newUnit->code}. Harga Kesepakatan: Rp " . number_format($dealPrice, 0, ',', '.'),
            ]);
        }

        // If unit changed and linked to lead, log activity
        if ($oldUnitId != $newUnit->id && $negotiation->lead_id) {
            $oldUnit = Unit::find($oldUnitId);
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "🔄 Unit pada Pengajuan Negosiasi diubah dari " . ($oldUnit ? $oldUnit->code : "Unit #{$oldUnitId}") . " ke {$newUnit->code}.",
            ]);
        }

        return back()->with('success', 'Pengajuan negosiasi & jawaban developer berhasil diperbarui!');
    }

    /**
     * CRM Internal: Review negotiation (approve / counter / reject)
     */
    public function review(Request $request, Negotiation $negotiation)
    {
        $request->validate([
            'action' => 'required|in:approve,counter,reject',
            'counter_price' => 'required_if:action,counter|nullable|numeric|min:0',
            'counter_notes' => 'nullable|string|max:1000',
            'form_data' => 'nullable|array',
        ]);

        $action = $request->action;

        if ($action === 'approve') {
            $approvalData = [
                'status' => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ];

            $existingFd = $request->has('form_data') && is_array($request->form_data)
                ? $request->form_data
                : (is_array($negotiation->form_data) ? $negotiation->form_data : []);

            $dealNumeric = null;
            if (!empty($existingFd['jawaban']['price'])) {
                $cleanedDeal = $this->parseDigitsToInt($existingFd['jawaban']['price']);
                if ($cleanedDeal && $cleanedDeal > 0) {
                    $dealNumeric = $cleanedDeal;
                }
            }
            if ($request->filled('counter_price')) {
                $dealNumeric = (int)$request->counter_price;
            }
            if ($dealNumeric) {
                $approvalData['counter_price'] = $dealNumeric;
            }

            if ($request->filled('counter_notes')) {
                $approvalData['counter_notes'] = $request->counter_notes;
            }

            $finalDealForForm = $dealNumeric ?: ($negotiation->counter_price ?: $negotiation->offered_price);
            $existingFd['jawaban'] = is_array($existingFd['jawaban'] ?? null) ? $existingFd['jawaban'] : [];
            if ($finalDealForForm > 0) {
                $existingFd['jawaban']['price'] = Negotiation::formatNominal($finalDealForForm);
            }
            $approvalData['form_data'] = $existingFd;

            $negotiation->update($approvalData);

            // Update lead status
            if ($negotiation->lead_id) {
                Lead::where('id', $negotiation->lead_id)->update(['status' => 'negotiation']);
                $dealPrice = $negotiation->resolveDealPrice();
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => "✅ Negosiasi DISETUJUI untuk unit " . ($negotiation->unit?->label ?? $negotiation->unit?->code ?? '-') . ". Harga Kesepakatan: Rp " . number_format($dealPrice, 0, ',', '.'),
                ]);
            }
        } elseif ($action === 'counter') {
            $existingFd = is_array($negotiation->form_data) ? $negotiation->form_data : [];
            $existingFd['jawaban'] = is_array($existingFd['jawaban'] ?? null) ? $existingFd['jawaban'] : [];
            $existingFd['jawaban']['price'] = Negotiation::formatNominal((int)$request->counter_price);
            $existingFd['jawaban']['show_jawaban'] = true;

            $negotiation->update([
                'status' => 'counter_offer',
                'counter_price' => $request->counter_price,
                'counter_notes' => $request->counter_notes,
                'form_data' => $existingFd,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'note',
                    'description' => "🔄 Counter Offer diberikan: Rp " . number_format($request->counter_price, 0, ',', '.') . " untuk unit " . ($negotiation->unit?->label ?? $negotiation->unit?->code ?? '-'),
                ]);
            }
        } else {
            $negotiation->update([
                'status' => 'rejected',
                'counter_notes' => $request->counter_notes,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => "❌ Negosiasi DITOLAK untuk unit " . ($negotiation->unit?->label ?? $negotiation->unit?->code ?? '-') . ($request->counter_notes ? " Alasan: {$request->counter_notes}" : ''),
                ]);
            }
        }

        return back()->with('success', match ($action) {
            'approve' => 'Negosiasi disetujui!',
            'counter' => 'Counter offer berhasil dikirim!',
            'reject' => 'Negosiasi ditolak.',
        });
    }

    /**
     * CRM Internal: Convert approved negotiation to booking
     */
    public function convertToBooking(Negotiation $negotiation)
    {
        if ($negotiation->status !== 'approved' && $negotiation->client_response !== 'accepted') {
            return back()->with('error', 'Hanya negosiasi yang disetujui yang bisa dikonversi ke booking.');
        }

        if ($negotiation->booking_id) {
            return redirect()->route('bookings.show', $negotiation->booking_id);
        }

        $unit = $negotiation->unit;
        if ($unit && $this->unitIsUnavailableForNewDeal($unit)) {
            return back()->with('error', "Unit {$unit->label} sudah berstatus {$unit->status} / ter-booking dan tidak dapat dikonversi lagi.");
        }

        $schemeMap = [
            'cash_keras' => 'cash',
            'cash_bertahap' => 'cash_installment',
            'kpr' => 'kpr',
        ];
        $paymentScheme = $schemeMap[$negotiation->payment_scheme] ?? ($negotiation->payment_scheme ?? 'kpr');

        $params = [
            'unit_id' => $negotiation->unit_id,
            'lead_id' => $negotiation->lead_id,
            'negotiation_id' => $negotiation->id,
            'final_price' => $negotiation->resolveDealPrice(),
            'payment_scheme' => $paymentScheme,
            'dp_amount' => $negotiation->dp_amount,
            'installment_months' => $negotiation->installment_months,
            'special_bonus_items' => $negotiation->special_bonus_items,
        ];

        $activeReservation = $negotiation->resolveLinkedReservation();
        if ($activeReservation && $activeReservation->status === 'active') {
            $params['reservation_id'] = $activeReservation->id;
            $params['reserved_amount'] = $activeReservation->amount;
        }

        return redirect()->route('bookings.create', $params);
    }

    // ===================================================================
    // PUBLIC ROUTES (No Auth)
    // ===================================================================

    /**
     * Public: Show negotiation form for client
     */
    public function publicForm($token)
    {
        $negotiation = Negotiation::with([
            'unit.unitType',
            'unit.project',
            'project',
            'creator.brokerCompany',
            'lead.assignedTo.brokerCompany',
            'lead.brokerCompany'
        ])
            ->where('token', $token)
            ->firstOrFail();

        // Check if expired
        if ($negotiation->isExpired() && $negotiation->status === 'draft') {
            $negotiation->update(['status' => 'expired']);
        }

        $negotiation->form_details = $negotiation->getFormDetails();

        $settings = [];
        $settingsRaw = \App\Models\Setting::all();
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = \App\Models\Setting::get($s->key);
        }

        return Inertia::render('Public/NegotiationForm', [
            'negotiation' => $negotiation,
            'settings' => [
                'company_name' => $settings['company_name'] ?? 'Homi Developer',
                'company_logo' => $settings['company_logo'] ?? null,
                'spr_special_offer' => $settings['spr_special_offer'] ?? null,
            ],
        ]);
    }

    /**
     * Public: Client submits their negotiation
     */
    public function publicSubmit(Request $request, $token)
    {
        $negotiation = Negotiation::where('token', $token)->firstOrFail();

        if ($negotiation->isExpired()) {
            $negotiation->update(['status' => 'expired']);
            return back()->with('error', 'Form negosiasi ini sudah kedaluwarsa.');
        }

        if (!in_array($negotiation->status, ['draft', 'counter_offer'])) {
            return back()->with('error', 'Form negosiasi ini sudah tidak bisa diubah.');
        }

        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_phone' => 'required|string|max:30',
            'client_email' => 'nullable|email|max:255',
            'offered_price' => 'required|numeric|min:1',
            'payment_scheme' => 'required|in:cash_keras,cash_bertahap,kpr',
            'dp_amount' => 'nullable|numeric|min:0',
            'installment_months' => 'nullable|integer|min:1|max:360',
            'special_requests' => 'nullable|string|max:2000',
            'special_bonus_items' => 'nullable|array',
            'custom_layout_options' => 'nullable|array',
            'custom_layout_notes' => 'nullable|string|max:2000',
            'client_signature' => 'nullable|string',
            'notes' => 'nullable|string|max:1000',
        ]);

        $existingFormData = is_array($negotiation->form_data) ? $negotiation->form_data : [];
        if (!isset($existingFormData['pengajuan']) || !is_array($existingFormData['pengajuan'])) {
            $existingFormData['pengajuan'] = [];
        }
        $existingFormData['pengajuan']['cara_bayar'] = Negotiation::formatSchemeLabel(
            $validated['payment_scheme'],
            isset($validated['installment_months']) ? (int)$validated['installment_months'] : null
        );
        $existingFormData['pengajuan']['price'] = Negotiation::formatNominal((int)$validated['offered_price']);
        if (array_key_exists('dp_amount', $validated) && $validated['dp_amount'] !== null && (int)$validated['dp_amount'] > 0) {
            $existingFormData['pengajuan']['dp1_amount'] = Negotiation::formatNominal((int)$validated['dp_amount']);
        }
        if (array_key_exists('special_bonus_items', $validated)) {
            $cleanBonuses = array_values(array_filter(array_map('trim', $validated['special_bonus_items'] ?? [])));
            $existingFormData['bonus'] = count($cleanBonuses) > 0 ? '(' . implode(', ', $cleanBonuses) . ')' : '-';
        }
        $syncedFormData = $this->buildSyncedFormData(
            $existingFormData,
            (int)$validated['offered_price'],
            $validated['payment_scheme'],
            isset($validated['dp_amount']) ? (int)$validated['dp_amount'] : null,
            isset($validated['installment_months']) ? (int)$validated['installment_months'] : null,
            $validated['special_bonus_items'] ?? ($negotiation->special_bonus_items ?? []),
            $negotiation->counter_price ? (int)$negotiation->counter_price : null,
            $validated['client_name']
        );

        $negotiation->update(array_merge($validated, [
            'status' => 'pending',
            'form_data' => $syncedFormData,
        ]));

        // Auto-link to existing lead if phone matches
        if (!$negotiation->lead_id) {
            $existingLead = Lead::where('phone', $validated['client_phone'])->first();
            if ($existingLead) {
                $negotiation->update(['lead_id' => $existingLead->id]);
            }
        }

        // Log activity
        if ($negotiation->lead_id) {
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => $negotiation->created_by,
                'type' => 'note',
                'description' => "📋 Client mengajukan negosiasi: Rp " . number_format($validated['offered_price'], 0, ',', '.') . " untuk unit " . ($negotiation->unit?->label ?? $negotiation->unit?->code ?? '-') . " (Skema: {$validated['payment_scheme']})",
            ]);

            // Update lead status to negotiation
            Lead::where('id', $negotiation->lead_id)->update(['status' => 'negotiation']);
        }

        return back()->with('success', 'Pengajuan negosiasi berhasil dikirim! Kami akan meninjau dalam 1x24 jam.');
    }

    /**
     * Public: Client responds to counter offer
     */
    public function publicCounterResponse(Request $request, $token)
    {
        $negotiation = Negotiation::where('token', $token)->firstOrFail();

        if ($negotiation->status !== 'counter_offer') {
            return back()->with('error', 'Tidak ada counter offer yang menunggu respons.');
        }

        $request->validate([
            'response' => 'required|in:accepted,rejected,revised',
            'offered_price' => 'required_if:response,revised|nullable|numeric|min:1',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($request->response === 'accepted') {
            $existingFd = is_array($negotiation->form_data) ? $negotiation->form_data : [];
            $existingFd['jawaban'] = is_array($existingFd['jawaban'] ?? null) ? $existingFd['jawaban'] : [];
            $existingFd['jawaban']['price'] = Negotiation::formatNominal((int)$negotiation->counter_price);

            $negotiation->update([
                'client_response' => 'accepted',
                'client_response_at' => now(),
                'offered_price' => $negotiation->counter_price, // Accept counter price
                'status' => 'approved',
                'form_data' => $existingFd,
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'status_change',
                    'description' => "✅ Client MENERIMA counter offer: Rp " . number_format($negotiation->counter_price, 0, ',', '.'),
                ]);
            }
        } elseif ($request->response === 'revised') {
            $existingFd = is_array($negotiation->form_data) ? $negotiation->form_data : [];
            $existingFd['pengajuan'] = is_array($existingFd['pengajuan'] ?? null) ? $existingFd['pengajuan'] : [];
            $existingFd['pengajuan']['price'] = Negotiation::formatNominal((int)$request->offered_price);
            $existingFd['jawaban'] = is_array($existingFd['jawaban'] ?? null) ? $existingFd['jawaban'] : [];
            $existingFd['jawaban']['price'] = '-';

            $negotiation->update([
                'client_response' => 'revised',
                'client_response_at' => now(),
                'offered_price' => $request->offered_price,
                'notes' => $request->notes,
                'status' => 'pending', // Back to pending for re-review
                'counter_price' => null,
                'counter_notes' => null,
                'form_data' => $existingFd,
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'note',
                    'description' => "🔄 Client mengajukan harga revisi: Rp " . number_format($request->offered_price, 0, ',', '.'),
                ]);
            }
        } else {
            $negotiation->update([
                'client_response' => 'rejected',
                'client_response_at' => now(),
                'status' => 'rejected',
            ]);

            if ($negotiation->lead_id) {
                LeadActivity::create([
                    'lead_id' => $negotiation->lead_id,
                    'user_id' => $negotiation->created_by,
                    'type' => 'status_change',
                    'description' => "❌ Client MENOLAK counter offer.",
                ]);
            }
        }

        return back()->with('success', match ($request->response) {
            'accepted' => 'Terima kasih! Anda telah menerima penawaran kami.',
            'revised' => 'Harga revisi Anda telah dikirim untuk ditinjau kembali.',
            'rejected' => 'Negosiasi ditutup. Terima kasih atas minat Anda.',
        });
    }

    /**
     * Delete / Soft delete negotiation draft
     */
    public function destroy(Negotiation $negotiation)
    {
        if ($negotiation->lead_id) {
            $unitCode = $negotiation->unit ? $negotiation->unit->code : '-';
            LeadActivity::create([
                'lead_id' => $negotiation->lead_id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => "🗑️ Form Negosiasi (Unit {$unitCode}) telah dihapus.",
            ]);
        }

        $negotiation->delete();

        return back()->with('success', 'Form negosiasi berhasil dihapus.');
    }

    // ===================================================================
    // PDF GENERATION & EXPORT
    // ===================================================================

    /**
     * Helper to fetch company & PDF settings
     */
    private function getSettings()
    {
        $settingsRaw = Setting::all();
        $settings = [];
        foreach ($settingsRaw as $s) {
            $settings[$s->key] = Setting::get($s->key);
        }
        $settings['spr_signatures'] = Setting::getSignatureConfig();
        $settings['standard_booking_fee'] = Setting::getStandardBookingFee();
        return $settings;
    }

    /**
     * Render PDF for public client access via token
     */
    public function publicPdf($token)
    {
        $negotiation = Negotiation::with(['unit.unitType', 'unit.project', 'project', 'creator', 'reviewer', 'lead'])
            ->where('token', $token)
            ->firstOrFail();

        return $this->generatePdfResponse($negotiation);
    }

    /**
     * Render PDF for CRM authenticated internal users
     */
    public function streamPdf(Negotiation $negotiation)
    {
        $negotiation->load(['unit.unitType', 'unit.project', 'project', 'creator', 'reviewer', 'lead']);

        return $this->generatePdfResponse($negotiation);
    }

    /**
     * Generate DomPDF Response
     */
    private function generatePdfResponse(Negotiation $negotiation)
    {
        $settings = $this->getSettings();
        $negotiation->update(['pdf_generated_at' => now()]);

        $clientSafe = str_replace(['/', '\\', ' '], '_', $negotiation->client_name);
        $unitCode = $negotiation->unit ? str_replace(['/', '\\', ' '], '_', $negotiation->unit->code) : 'UNIT';
        $fileName = "Proposal_Pengajuan_{$clientSafe}_{$unitCode}.pdf";

        if (request()->has('html') || request()->query('view') === 'html') {
            return view('pdf.negotiation', compact('negotiation', 'settings'));
        }

        $pdf = Pdf::loadView('pdf.negotiation', compact('negotiation', 'settings'))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('chroot', [public_path(), storage_path()]);

        if (request()->has('download')) {
            return $pdf->download($fileName);
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$fileName}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
