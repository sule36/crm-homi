<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'company_id', 'lead_id', 'phone', 'direction', 'message', 'type', 'status', 'platform',
    ];

    // Relationships
    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
