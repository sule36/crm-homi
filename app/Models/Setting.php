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
}
