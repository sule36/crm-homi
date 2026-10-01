<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FollowUpReminder extends Model
{
    use BelongsToTenant;

    protected $fillable = ['company_id', 'lead_id', 'user_id', 'remind_at', 'message', 'status'];

    protected function casts(): array
    {
        return ['remind_at' => 'datetime'];
    }

    public function lead() { return $this->belongsTo(Lead::class); }
    public function user() { return $this->belongsTo(User::class); }
}
