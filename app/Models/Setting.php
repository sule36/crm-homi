<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = ['company_id', 'key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (str_contains($key, '.')) {
            $parts = explode('.', $key, 2);
            $parentKey = $parts[0];
            $subKey = $parts[1];

            $setting = static::where('key', $parentKey)->first();
            if ($setting) {
                $val = json_decode($setting->value, true);
                if (is_array($val) && array_key_exists($subKey, $val)) {
                    return $val[$subKey];
                }
            }
        }

        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        $val = json_decode($setting->value, true);
        return json_last_error() === JSON_ERROR_NONE ? $val : $setting->value;
    }

    public static function set(string $key, mixed $value, ?int $companyId = null): static
    {
        $encoded = is_array($value) || is_object($value) ? json_encode($value) : (string)$value;
        $cid = $companyId ?? (auth()->hasUser() ? auth()->user()->company_id : null);
        
        $match = ['key' => $key];
        if ($cid) {
            $match['company_id'] = $cid;
        }

        return static::updateOrCreate($match, [
            'company_id' => $cid,
            'value' => $encoded,
        ]);
    }

    public static function isTruthy(mixed $value, bool $default = true): bool
    {
        if ($value === null) {
            return $default;
        }
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value !== 0;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['', '0', 'false', 'off', 'no', 'null'], true)) {
                return false;
            }
            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }
        }

        return $default;
    }

    public static function isInstagramLeadCaptureEnabled(?int $companyId = null): bool
    {
        $query = static::withoutGlobalScopes()->where('key', 'instagram_lead_capture_enabled');

        if ($companyId) {
            $scoped = (clone $query)->where('company_id', $companyId)->orderByDesc('updated_at')->orderByDesc('id')->first();
            if ($scoped) {
                return static::isTruthy($scoped->value, true);
            }
        }

        $latest = $query->orderByDesc('updated_at')->orderByDesc('id')->first();
        if (!$latest) {
            return true;
        }

        return static::isTruthy($latest->value, true);
    }

    /**
     * Get the tenant's fixed standard Booking Fee (UTJ) amount in IDR.
     */
    public static function getStandardBookingFee(int $default = 17000000): int
    {
        $raw = static::get('standard_booking_fee', null);
        if ($raw === null || $raw === '') {
            return $default;
        }
        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }
        $cleaned = preg_replace('/[^0-9]/', '', (string) $raw);

        return $cleaned !== '' ? max(0, (int) $cleaned) : $default;
    }
}
