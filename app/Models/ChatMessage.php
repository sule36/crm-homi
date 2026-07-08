<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = [
        'lead_id', 'phone', 'direction', 'message', 'type', 'status',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
