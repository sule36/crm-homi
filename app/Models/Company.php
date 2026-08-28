<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'domain',
        'email',
        'phone',
        'address',
        'subscription_plan',
        'status',
        'max_users',
        'max_projects',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'max_users' => 'integer',
            'max_projects' => 'integer',
        ];
    }

    public function users() { return $this->hasMany(User::class); }
    public function projects() { return $this->hasMany(Project::class); }
    public function leads() { return $this->hasMany(Lead::class); }
    public function bookings() { return $this->hasMany(Booking::class); }
    public function expenses() { return $this->hasMany(Expense::class); }
}
