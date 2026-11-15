<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use App\Traits\BelongsToTenant;

class Unit extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'company_id', 'project_id', 'unit_type_id', 'block', 'number', 'floor',
        'status', 'facing_direction', 'premium_charge', 'final_price',
        'promo', 'discount_amount', 'carport', 'net_price', 'commission_notes',
        'management_notes', 'siteplan_coordinates',
        'held_by', 'held_until', 'notes',
        'certificate_status', 'certificate_number', 'imb_number', 'pbb_number', 'legal_notes',
    ];

    protected $appends = ['unit_number', 'label', 'display_price'];

    protected function casts(): array
    {
        return [
            'final_price' => 'integer',
            'premium_charge' => 'integer',
            'discount_amount' => 'integer',
            'net_price' => 'integer',
            'carport' => 'integer',
            'siteplan_coordinates' => 'array',
            'held_until' => 'datetime',
        ];
    }

    protected static function booted()
    {
        static::created(function ($unit) {
            $unit->project?->recalculateUnits();
        });

        static::updated(function ($unit) {
            if ($unit->wasChanged('project_id') || $unit->wasChanged('status')) {
                $unit->project?->recalculateUnits();
                if ($unit->wasChanged('project_id') && $unit->getOriginal('project_id')) {
                    Project::find($unit->getOriginal('project_id'))?->recalculateUnits();
                }
            }
        });

        static::deleted(function ($unit) {
            $unit->project?->recalculateUnits();
        });

        static::restored(function ($unit) {
            $unit->project?->recalculateUnits();
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function unitType()
    {
        return $this->belongsTo(UnitType::class)->withTrashed();
    }

    public function heldByUser()
    {
        return $this->belongsTo(User::class, 'held_by');
    }

    public function booking()
    {
        return $this->hasOne(Booking::class)->latestOfMany();
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeSelectableForLov($query, int|array|null $includeUnitIds = null)
    {
        $ids = is_array($includeUnitIds)
            ? array_values(array_filter(array_map('intval', $includeUnitIds)))
            : ($includeUnitIds ? [(int) $includeUnitIds] : []);

        return $query->where(function ($outer) use ($ids) {
            $outer->where(function ($avail) {
                $avail->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('sold', 'booked', 'reserved', 'hold', 'cancelled')")
                    ->whereDoesntHave('bookings', function ($bq) {
                        $bq->withoutGlobalScopes()
                            ->whereNull('deleted_at')
                            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) IN ('pending', 'approved', 'completed', 'booked', 'sold')");
                    })
                    ->whereDoesntHave('reservations', function ($rq) {
                        $rq->withoutGlobalScopes()
                            ->whereNull('deleted_at')
                            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'");
                    });
            });
            if (!empty($ids)) {
                $outer->orWhereIn('units.id', $ids);
            }
        });
    }

    /**
     * Recompute and persist authoritative unit occupancy status from non-cancelled bookings & active reservations.
     */
    public function syncStatusFromRecords(?int $ignoreBookingId = null, ?int $ignoreReservationId = null): string
    {
        $bookings = Booking::withoutGlobalScopes()
            ->where('unit_id', $this->id)
            ->whereNull('deleted_at')
            ->when($ignoreBookingId, fn ($q) => $q->where('id', '!=', $ignoreBookingId))
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) NOT IN ('cancelled', 'rejected', 'expired')")
            ->get(['id', 'status', 'booked_by']);

        $hasCompleted = $bookings->contains(fn ($b) => in_array(strtolower(trim((string) $b->status)), ['completed', 'sold'], true));
        if ($hasCompleted) {
            $this->update(['status' => 'sold', 'held_by' => null, 'held_until' => null]);
            return 'sold';
        }

        $approvedBooking = $bookings->first(fn ($b) => in_array(strtolower(trim((string) $b->status)), ['approved', 'booked'], true));
        if ($approvedBooking) {
            $this->update(['status' => 'booked']);
            return 'booked';
        }

        $pendingBooking = $bookings->first(fn ($b) => strtolower(trim((string) $b->status)) === 'pending');
        if ($pendingBooking) {
            $this->update([
                'status' => 'hold',
                'held_by' => $pendingBooking->booked_by ?? $this->held_by,
                'held_until' => $this->held_until ?? now()->addDays(2),
            ]);
            return 'hold';
        }

        $activeReservation = Reservation::withoutGlobalScopes()
            ->where('unit_id', $this->id)
            ->whereNull('deleted_at')
            ->when($ignoreReservationId, fn ($q) => $q->where('id', '!=', $ignoreReservationId))
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'active'")
            ->latest('id')
            ->first();

        if ($activeReservation) {
            try {
                $this->update([
                    'status' => 'reserved',
                    'held_by' => $activeReservation->created_by ?? $this->held_by,
                    'held_until' => $activeReservation->expires_at ?? now()->addDays(7),
                ]);
                return 'reserved';
            } catch (\Throwable $e) {
                $this->update([
                    'status' => 'hold',
                    'held_by' => $activeReservation->created_by ?? $this->held_by,
                    'held_until' => $activeReservation->expires_at ?? now()->addDays(7),
                ]);
                return 'hold';
            }
        }

        $this->update(['status' => 'available', 'held_by' => null, 'held_until' => null]);
        return 'available';
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class)->latest();
    }

    public function latestReservation()
    {
        return $this->hasOne(Reservation::class)->latestOfMany();
    }

    public function progressHistory()
    {
        return $this->hasMany(UnitProgress::class)->orderBy('recorded_date', 'desc')->orderBy('created_at', 'desc');
    }

    public function latestProgress()
    {
        return $this->hasOne(UnitProgress::class)->latestOfMany();
    }

    public function priceHistories()
    {
        return $this->hasMany(UnitPriceHistory::class)->with('user')->latest();
    }

    public function statusHistories()
    {
        return $this->hasMany(UnitStatusHistory::class)->with('user')->latest();
    }

    // Label: "Blok A No. 01"
    public function getLabelAttribute(): string
    {
        $parts = [];
        if ($this->block) $parts[] = "Blok {$this->block}";
        if ($this->floor) $parts[] = "Lt. {$this->floor}";
        $parts[] = "No. {$this->number}";
        return implode(' ', $parts);
    }

    public function getUnitNumberAttribute(): string
    {
        return $this->getLabelAttribute();
    }

    public function getDisplayPriceAttribute(): int
    {
        return $this->final_price ?: ($this->unitType?->current_price ?? 0) + $this->premium_charge;
    }

    /**
     * Order units cleanly across both MySQL and SQLite by block and numeric unit number.
     */
    public function scopeOrderByUnitNumber($query)
    {
        $castType = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';

        return $query->orderBy('block')->orderByRaw("CAST(number AS {$castType}) ASC");
    }
}
