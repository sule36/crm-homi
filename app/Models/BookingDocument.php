<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class BookingDocument extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'booking_id', 'type', 'name', 'file_path'];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
