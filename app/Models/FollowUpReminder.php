<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FollowUpReminder extends Model
{
    protected $fillable = ['lead_id', 'user_id', 'remind_at', 'message', 'status'];

    protected function casts(): array
    {
        return ['remind_at' => 'datetime'];
    }

    public function lead() { return $this->belongsTo(Lead::class); }
    public function user() { return $this->belongsTo(User::class); }
}
