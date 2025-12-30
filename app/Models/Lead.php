<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class Lead extends Model
{
    protected $fillable = [
        'full_name',
        'email',
        'position',
        'company',
        'location',
        'profile_url',
        'followers',
        'connections',
        'education',
        'personal_message',
        'message_length',
        'generated_at',
        'total_leads',
        'comments',
        'organisation_id',
        'status',
        'treated', // ✅ new attribute
    ];

    protected $dates = ['generated_at'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function scopeQualified($query)
    {
        return $query->where('status', 'qualified');
    }

    public function scopeArchived($query)
    {
        return $query->where('status', 'archived');
    }

    public function scopeToBeTreated($query)
    {
        return $query->where('status', 'to_be_treated');
    }

    public function scopeTreated($query)
    {
        return $query->where('treated', true);
    }

    public function scopeUntreated($query)
    {
        return $query->where('treated', false);
    }
}
