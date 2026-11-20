<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Lead;
use App\Models\Negotiation;
use App\Models\Project;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\Unit;
use Illuminate\Support\Facades\Schema;

class DealSyncService
{
    /**
     * Resolve an existing Lead by ID or phone number, or auto-create one so client biodata is never orphaned.
     */
    public static function resolveOrCreateLead(?int $leadId, array $biodata, ?int $companyId = null, ?int $projectId = null): ?Lead
    {
        $lead = $leadId ? Lead::find($leadId) : null;

        if (!$lead && !empty($biodata['phone'])) {
            $rawPhone = trim((string) $biodata['phone']);
            $digits = preg_replace('/[^0-9]/', '', $rawPhone);
            $tail = strlen($digits) >= 8 ? substr($digits, -8) : $digits;

            $lead = Lead::when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->where(function ($q) use ($rawPhone, $tail) {
                    $q->where('phone', $rawPhone);
                    if ($tail !== '') {
                        $q->orWhere('phone', 'like', "%{$tail}");
                    }
                })
                ->latest('id')
                ->first();
        }

        if (!$lead && !empty($biodata['name']) && !empty($biodata['phone'])) {
            $createData = [
                'company_id' => $companyId ?: (auth()->user()?->company_id ?? 1),
                'project_id' => $projectId,
                'assigned_to' => $biodata['assigned_to'] ?? auth()->id(),
                'inhouse_pic_id' => $biodata['inhouse_pic_id'] ?? null,
                'name' => trim((string) $biodata['name']),
                'phone' => trim((string) $biodata['phone']),
                'email' => !empty($biodata['email']) ? trim((string) $biodata['email']) : null,
                'identity_number' => !empty($biodata['identity_number']) ? trim((string) $biodata['identity_number']) : null,
                'npwp' => !empty($biodata['npwp']) ? trim((string) $biodata['npwp']) : null,
                'address' => !empty($biodata['address']) ? trim((string) $biodata['address']) : null,
                'job' => !empty($biodata['job']) ? trim((string) $biodata['job']) : null,
                'source' => $biodata['source'] ?? 'walk_in',
                'status' => $biodata['status'] ?? 'reservation',
                'score' => 50,
            ];

            $safeCreate = [];
            foreach ($createData as $col => $val) {
                if (Schema::hasColumn('leads', $col)) {
                    $safeCreate[$col] = $val;
                }
            }
            $lead = Lead::create($safeCreate);
        }

        return $lead;
    }

    /**
     * Synchronize client biodata bi-directionally across Lead, Reservations, Negotiations, and Bookings.
     */
    public static function syncBiodataAcrossDeal(?Lead $lead, array $biodata, ?int $projectId = null): ?Lead
    {
        if (!$lead) {
            return null;
        }

        $name = isset($biodata['name']) && trim((string) $biodata['name']) !== '' ? trim((string) $biodata['name']) : null;
        $phone = isset($biodata['phone']) && trim((string) $biodata['phone']) !== '' ? trim((string) $biodata['phone']) : null;
        $email = array_key_exists('email', $biodata) && $biodata['email'] !== null && trim((string) $biodata['email']) !== ''
            ? trim((string) $biodata['email'])
            : null;
        $nik = isset($biodata['identity_number']) && trim((string) $biodata['identity_number']) !== ''
            ? trim((string) $biodata['identity_number'])
            : null;
        $npwp = isset($biodata['npwp']) && trim((string) $biodata['npwp']) !== ''
            ? trim((string) $biodata['npwp'])
            : null;
        $address = isset($biodata['address']) && trim((string) $biodata['address']) !== ''
            ? trim((string) $biodata['address'])
            : null;
        $job = isset($biodata['job']) && trim((string) $biodata['job']) !== ''
            ? trim((string) $biodata['job'])
            : null;

        // 1. Update Lead master record
        $leadUpdates = [];
        if ($name !== null) $leadUpdates['name'] = $name;
        if ($phone !== null) $leadUpdates['phone'] = $phone;
        if ($email !== null) $leadUpdates['email'] = $email;
        if ($nik !== null && Schema::hasColumn('leads', 'identity_number')) $leadUpdates['identity_number'] = $nik;
        if ($npwp !== null && Schema::hasColumn('leads', 'npwp')) $leadUpdates['npwp'] = $npwp;
        if ($address !== null && Schema::hasColumn('leads', 'address')) $leadUpdates['address'] = $address;
        if ($job !== null && Schema::hasColumn('leads', 'job')) $leadUpdates['job'] = $job;
        if ($projectId && empty($lead->project_id)) $leadUpdates['project_id'] = $projectId;

        if (!empty($leadUpdates)) {
            $lead->update($leadUpdates);
            $lead->refresh();
        }

        // Effective values from the updated Lead
        $effName = $lead->name;
        $effPhone = $lead->phone;
        $effEmail = $lead->email;
        $effNik = $lead->identity_number;
        $effNpwp = $lead->npwp;
        $effAddress = $lead->address;
        $effJob = $lead->job;

        // 2. Sync to linked Reservations (active or converted)
        if (Schema::hasTable('reservations')) {
            $reservations = Reservation::where('lead_id', $lead->id)
                ->whereIn('status', ['active', 'converted'])
                ->get();

            foreach ($reservations as $res) {
                $rUp = [];
                if ($effName && $res->client_name !== $effName) $rUp['client_name'] = $effName;
                if ($effPhone && $res->client_phone !== $effPhone) $rUp['client_phone'] = $effPhone;
                if ($effEmail && $res->client_email !== $effEmail) $rUp['client_email'] = $effEmail;
                if ($effNik && Schema::hasColumn('reservations', 'client_nik') && $res->client_nik !== $effNik) {
                    $rUp['client_nik'] = $effNik;
                }
                if (!empty($rUp)) {
                    $res->update($rUp);
                }
            }
        }

        // 3. Sync to linked Negotiations
        if (Schema::hasTable('negotiations')) {
            $negotiations = Negotiation::where('lead_id', $lead->id)
                ->whereNotIn('status', ['rejected', 'expired'])
                ->get();

            foreach ($negotiations as $nego) {
                $nUp = [];
                if ($effName && $nego->client_name !== $effName) $nUp['client_name'] = $effName;
                if ($effPhone && $nego->client_phone !== $effPhone) $nUp['client_phone'] = $effPhone;
                if ($effEmail && $nego->client_email !== $effEmail) $nUp['client_email'] = $effEmail;

                if ($effName && is_array($nego->form_data)) {
                    $fd = $nego->form_data;
                    $fdChanged = false;
                    if (($fd['dari'] ?? null) !== $effName) {
                        $fd['dari'] = $effName;
                        $fdChanged = true;
                    }
                    if (isset($fd['jawaban']) && is_array($fd['jawaban']) && ($fd['jawaban']['sig_pengaju_name'] ?? null) !== $effName) {
                        $fd['jawaban']['sig_pengaju_name'] = $effName;
                        $fdChanged = true;
                    }
                    if ($fdChanged) {
                        $nUp['form_data'] = $fd;
                    }
                }

                if (!empty($nUp)) {
                    $nego->update($nUp);
                }
            }
        }

        // 4. Sync to linked Bookings
        if (Schema::hasTable('bookings')) {
            $bookings = Booking::where('lead_id', $lead->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->get();

            foreach ($bookings as $booking) {
                $bUp = [];
                if ($effName && empty($booking->secondary_name)) {
                    if ($booking->sig4_name && $booking->sig4_name !== $effName) {
                        $bUp['sig4_image'] = null;
                        $bUp['customer_signed_at'] = null;
                    }
                    if ($booking->sig4_name !== $effName) {
                        $bUp['sig4_name'] = $effName;
                    }
                }
                if ($effNik && $booking->buyer_nik !== $effNik) $bUp['buyer_nik'] = $effNik;
                if ($effNpwp && $booking->buyer_npwp !== $effNpwp) $bUp['buyer_npwp'] = $effNpwp;
                if ($effAddress && $booking->buyer_address !== $effAddress) $bUp['buyer_address'] = $effAddress;
                if ($effJob && $booking->buyer_job !== $effJob) $bUp['buyer_job'] = $effJob;

                if (!empty($bUp)) {
                    $booking->update($bUp);
                }
            }
        }

        return $lead;
    }

    /**
     * Resolve 3-letter project code consistently across RSV, NG, SPR, and KW numbers.
     */
    public static function resolveProjectCode(?Project $project, ?int $projectId = null): string
    {
        if (!$project && $projectId) {
            $project = Project::withoutGlobalScopes()->find($projectId);
        }
        if (!$project) {
            $project = Project::withoutGlobalScopes()->first();
        }
        if ($project) {
            if (!empty($project->code)) {
                return strtoupper(trim($project->code));
            }
            $cleanName = preg_replace('/[^A-Za-z0-9]/', '', (string) $project->name);
            return strtoupper(substr($cleanName, 0, 3)) ?: 'ALC';
        }
        return 'ALC';
    }

    /**
     * Auto-update document number when project or date changes while preserving sequence number.
     */
    public static function syncDocumentNumber(
        ?string $currentNumber,
        string $prefix,
        ?Project $project,
        ?int $projectId,
        string $table,
        string $column,
        ?int $ignoreId = null,
        ?\DateTimeInterface $date = null
    ): ?string {
        if (empty($currentNumber)) {
            return $currentNumber;
        }

        $newCode = self::resolveProjectCode($project, $projectId);
        $updated = $currentNumber;

        // Replace project code in patterns like 001/RSV-OLD/XI/2026 or 001/SPR-OLD/IX/2026
        $pattern = '/^(\d+\/' . preg_quote($prefix, '/') . '-)([A-Z0-9]+)(\/(?:I|II|III|IV|V|VI|VII|VIII|IX|X|XI|XII)\/\d{4})$/i';
        if (preg_match($pattern, $updated, $m)) {
            $romanPart = $m[3];
            if ($date) {
                $romanMonths = [
                    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI',
                    7 => 'VII', 8 => 'VIII', 9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII',
                ];
                $mRoman = $romanMonths[(int) $date->format('n')] ?? 'IX';
                $yr = $date->format('Y');
                $romanPart = "/{$mRoman}/{$yr}";
            }
            $updated = $m[1] . $newCode . $romanPart;
        }

        if ($updated === $currentNumber) {
            return $currentNumber;
        }

        // Ensure uniqueness in target table
        $candidate = $updated;
        if (preg_match('/^(\d+)(\/.*)$/', $updated, $parts)) {
            $seq = (int) $parts[1];
            $padLen = strlen($parts[1]);
            $suffix = $parts[2];
            $attempts = 0;
            while (
                \Illuminate\Support\Facades\DB::table($table)
                    ->where($column, $candidate)
                    ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                    ->exists()
                && $attempts < 500
            ) {
                $seq++;
                $attempts++;
                $candidate = sprintf('%0' . $padLen . 'd', $seq) . $suffix;
            }
        }

        return $candidate;
    }

    /**
     * Synchronize Unit change across linked Reservation, Negotiation, and Booking records so all documents match 100%.
     */
    public static function syncUnitChangeAcrossDeal(
        Unit $newUnit,
        ?int $oldUnitId,
        ?int $leadId,
        ?Reservation $sourceReservation = null,
        ?Negotiation $sourceNegotiation = null,
        ?Booking $sourceBooking = null
    ): void {
        $newUnit->loadMissing(['project', 'unitType']);
        $project = $newUnit->project;
        $unitType = $newUnit->unitType;
        $listedAmount = (int) ($newUnit->display_price ?: $newUnit->final_price ?: $unitType?->current_price ?: 0);

        // 1. Update Lead project_id if linked
        if ($leadId) {
            Lead::where('id', $leadId)->update(['project_id' => $newUnit->project_id]);
        }

        // 2. Sync linked Reservations (if not the source itself)
        if (!$sourceReservation && ($leadId || $sourceBooking || $sourceNegotiation)) {
            $resQuery = Reservation::whereIn('status', ['active', 'converted']);
            $resQuery->where(function ($q) use ($leadId, $oldUnitId, $sourceBooking, $sourceNegotiation) {
                if ($sourceBooking && Schema::hasColumn('reservations', 'booking_id')) {
                    $q->orWhere('booking_id', $sourceBooking->id);
                }
                if ($sourceNegotiation && Schema::hasColumn('reservations', 'negotiation_id')) {
                    $q->orWhere('negotiation_id', $sourceNegotiation->id);
                }
                if ($leadId && $oldUnitId) {
                    $q->orWhere(fn ($sq) => $sq->where('lead_id', $leadId)->where('unit_id', $oldUnitId));
                }
            });

            $linkedReservations = $resQuery->get();
            foreach ($linkedReservations as $res) {
                $overrides = is_array($res->custom_overrides) ? $res->custom_overrides : [];
                foreach (['unit_code', 'unit_type_name', 'spec_text', 'project_name'] as $staleKey) {
                    unset($overrides[$staleKey]);
                }
                $newResNum = self::syncDocumentNumber(
                    $res->reservation_number,
                    'RSV',
                    $project,
                    $newUnit->project_id,
                    'reservations',
                    'reservation_number',
                    $res->id
                );
                $res->update([
                    'unit_id' => $newUnit->id,
                    'project_id' => $newUnit->project_id,
                    'reservation_number' => $newResNum ?: $res->reservation_number,
                    'custom_overrides' => !empty($overrides) ? $overrides : null,
                ]);
            }
        } elseif ($sourceReservation) {
            $newResNum = self::syncDocumentNumber(
                $sourceReservation->reservation_number,
                'RSV',
                $project,
                $newUnit->project_id,
                'reservations',
                'reservation_number',
                $sourceReservation->id
            );
            if ($newResNum && $newResNum !== $sourceReservation->reservation_number) {
                $sourceReservation->update(['reservation_number' => $newResNum]);
            }
        }

        // 3. Sync linked Negotiations
        if (!$sourceNegotiation && ($leadId || $sourceReservation || $sourceBooking)) {
            $negoQuery = Negotiation::whereNotIn('status', ['rejected', 'expired']);
            $negoQuery->where(function ($q) use ($leadId, $oldUnitId, $sourceReservation, $sourceBooking) {
                if ($sourceBooking && Schema::hasColumn('negotiations', 'booking_id')) {
                    $q->orWhere('booking_id', $sourceBooking->id);
                }
                if ($sourceReservation && $sourceReservation->negotiation_id) {
                    $q->orWhere('id', $sourceReservation->negotiation_id);
                }
                if ($leadId && $oldUnitId) {
                    $q->orWhere(fn ($sq) => $sq->where('lead_id', $leadId)->where('unit_id', $oldUnitId));
                }
            });

            $linkedNegotiations = $negoQuery->get();
            foreach ($linkedNegotiations as $nego) {
                $fd = is_array($nego->form_data) ? $nego->form_data : [];
                if ($project) {
                    $fd['project_name'] = $project->name;
                    if (!empty($project->address)) {
                        $fd['project_address'] = $project->address;
                    }
                }
                $fd['kavling'] = $newUnit->label;
                $fd['type'] = $unitType?->name ?? ($fd['type'] ?? '-');
                if ($unitType?->land_area ?? $newUnit->land_area ?? null) {
                    $fd['luas_tanah'] = (string) ($unitType?->land_area ?? $newUnit->land_area);
                }
                if ($unitType?->building_area ?? $newUnit->building_area ?? null) {
                    $fd['luas_bangunan'] = (string) ($unitType?->building_area ?? $newUnit->building_area);
                }
                if ($listedAmount > 0) {
                    $fd['price_list'] = Negotiation::formatNominal($listedAmount);
                }

                $newNegoNum = self::syncDocumentNumber(
                    $nego->negotiation_number,
                    'NG',
                    $project,
                    $newUnit->project_id,
                    'negotiations',
                    'negotiation_number',
                    $nego->id
                );

                $nego->update([
                    'unit_id' => $newUnit->id,
                    'project_id' => $newUnit->project_id,
                    'unit_listed_price' => $listedAmount ?: $nego->unit_listed_price,
                    'negotiation_number' => $newNegoNum ?: $nego->negotiation_number,
                    'form_data' => $fd,
                ]);
            }
        } elseif ($sourceNegotiation) {
            $newNegoNum = self::syncDocumentNumber(
                $sourceNegotiation->negotiation_number,
                'NG',
                $project,
                $newUnit->project_id,
                'negotiations',
                'negotiation_number',
                $sourceNegotiation->id
            );
            if ($newNegoNum && $newNegoNum !== $sourceNegotiation->negotiation_number) {
                $sourceNegotiation->update(['negotiation_number' => $newNegoNum]);
            }
        }

        // 4. Sync linked Bookings (if source was Reservation/Negotiation or update Booking number if source was Booking)
        if ($sourceBooking) {
            $rawSpk = $sourceBooking->getRawOriginal('spk_number') ?: $sourceBooking->spk_number;
            $newSpkNum = self::syncDocumentNumber(
                $rawSpk,
                'SPR',
                $project,
                $newUnit->project_id,
                'bookings',
                'spk_number',
                $sourceBooking->id,
                $sourceBooking->spr_date ?? $sourceBooking->booking_date
            );
            if ($newSpkNum && $newSpkNum !== $rawSpk) {
                $sourceBooking->update(['spk_number' => $newSpkNum]);
            }
        }
    }

    /**
     * When a Reservation amount is updated, sync it to any linked Negotiation form_data and Booking credit.
     */
    public static function syncReservationAmountToNegotiations(Reservation $reservation): void
    {
        if (!$reservation->lead_id && !$reservation->unit_id && !$reservation->negotiation_id) {
            return;
        }

        $standardBookingFee = Setting::getStandardBookingFee();
        $resAmount = (int) $reservation->amount;
        $resFormatted = $resAmount > 0 ? Negotiation::formatNominal($resAmount) : '-';
        $remainingUtj = max(0, $standardBookingFee - $resAmount);
        $bfFormatted = $remainingUtj > 0
            ? Negotiation::formatNominal($remainingUtj)
            : ($standardBookingFee > 0 ? '0,- (Lunas UTJ)' : '-');
        $bfTotalFormatted = '(Total ' . Negotiation::formatNominal($standardBookingFee) . ')';

        $negoQuery = Negotiation::whereNotIn('status', ['rejected', 'expired'])
            ->where(function ($q) use ($reservation) {
                if ($reservation->negotiation_id) {
                    $q->orWhere('id', $reservation->negotiation_id);
                }
                if ($reservation->lead_id && $reservation->unit_id) {
                    $q->orWhere(fn ($sq) => $sq->where('lead_id', $reservation->lead_id)->where('unit_id', $reservation->unit_id));
                } elseif ($reservation->lead_id) {
                    $q->orWhere('lead_id', $reservation->lead_id);
                }
            });

        foreach ($negoQuery->get() as $nego) {
            if (!is_array($nego->form_data)) {
                continue;
            }
            $fd = $nego->form_data;
            $fd['reservasi'] = $resFormatted;
            if (isset($fd['pengajuan']) && is_array($fd['pengajuan'])) {
                $fd['pengajuan']['reservasi'] = $resFormatted;
                $fd['pengajuan']['booking_fee'] = $bfFormatted;
                $fd['pengajuan']['booking_fee_total'] = $bfTotalFormatted;
            }
            if (isset($fd['jawaban']) && is_array($fd['jawaban'])) {
                $fd['jawaban']['reservasi'] = $resFormatted;
                $fd['jawaban']['booking_fee'] = $bfFormatted;
                $fd['jawaban']['booking_fee_total'] = $bfTotalFormatted;
            }
            $nego->update(['form_data' => $fd]);
        }
    }
}

