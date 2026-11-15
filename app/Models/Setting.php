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
     * Get canonical SPR / Document Signature settings merged with safe defaults.
     */
    public static function getSignatureConfig(): array
    {
        $defaults = [
            'city' => 'Jakarta Selatan',
            'sig1_title' => 'Sales Manager',
            'sig1_name' => 'Dhany Nur',
            'sig1_image' => null,
            'sig2_title' => 'Direktur',
            'sig2_name' => 'Luhur Wira Pramudya',
            'sig2_image' => null,
            'finance_title' => 'Finance & Kasir',
            'finance_name' => '',
            'finance_image' => null,
            'reservation_signer_slot' => 'sig1',
            'sig3_title' => 'SALES',
            'sig3_name' => '',
            'sig3_image' => null,
            'sig4_title' => 'Pemesan / Pembeli',
            'sig4_name' => '',
            'sig4_image' => null,
        ];

        $stored = static::get('spr_signatures', []);
        if (!is_array($stored)) {
            $stored = [];
        }

        $merged = array_merge($defaults, $stored);

        // Migrate legacy placeholder label on sig3 (used as Sales slot on SPR)
        if (($merged['sig3_title'] ?? '') === 'Pemesan Utama') {
            $merged['sig3_title'] = 'SALES';
        }
        if (!in_array($merged['reservation_signer_slot'] ?? '', ['finance', 'sig1', 'sig2'], true)) {
            $merged['reservation_signer_slot'] = 'sig1';
        }

        return $merged;
    }

    /**
     * Return structured official developer signatory roles for dropdowns across documents.
     */
    public static function getDeveloperSignatories(): array
    {
        $cfg = static::getSignatureConfig();

        return [
            [
                'key' => 'finance',
                'label' => 'Finance / Penerima Dana PT',
                'title' => $cfg['finance_title'] ?: 'Finance & Kasir',
                'name' => trim((string) ($cfg['finance_name'] ?? '')),
                'image' => $cfg['finance_image'] ?? null,
                'is_configured' => trim((string) ($cfg['finance_name'] ?? '')) !== '',
            ],
            [
                'key' => 'sig1',
                'label' => 'Operasional / Sales Manager PT',
                'title' => $cfg['sig1_title'] ?: 'Sales Manager',
                'name' => trim((string) ($cfg['sig1_name'] ?? '')),
                'image' => $cfg['sig1_image'] ?? null,
                'is_configured' => trim((string) ($cfg['sig1_name'] ?? '')) !== '',
            ],
            [
                'key' => 'sig2',
                'label' => 'Manajemen / Pimpinan PT',
                'title' => $cfg['sig2_title'] ?: 'Direktur',
                'name' => trim((string) ($cfg['sig2_name'] ?? '')),
                'image' => $cfg['sig2_image'] ?? null,
                'is_configured' => trim((string) ($cfg['sig2_name'] ?? '')) !== '',
            ],
        ];
    }

    /**
     * Resolve a developer signatory preset by slot (or default reservation signer slot).
     */
    public static function resolveDeveloperSignatory(?string $slot = null): array
    {
        $cfg = static::getSignatureConfig();
        $targetSlot = in_array($slot, ['finance', 'sig1', 'sig2'], true)
            ? $slot
            : ($cfg['reservation_signer_slot'] ?? 'sig1');

        $byKey = collect(static::getDeveloperSignatories())->keyBy('key');
        $chosen = $byKey->get($targetSlot) ?? $byKey->get('sig1');

        // If finance slot is selected as default but name hasn't been configured yet, fallback gracefully to sig1
        if (($chosen['key'] ?? '') === 'finance' && empty($chosen['name']) && !empty($byKey->get('sig1')['name'])) {
            $chosen = $byKey->get('sig1');
        }

        return [
            'slot' => $chosen['key'] ?? 'sig1',
            'title' => $chosen['title'] ?? 'Sales Manager',
            'name' => $chosen['name'] ?? '',
            'image' => $chosen['image'] ?? null,
        ];
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
