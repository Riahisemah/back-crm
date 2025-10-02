<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'title',
        'description',
        'date',
        'time',
        'duration',
        'type',
        'status',
        'location',
        'attendees',
        'related_to',
            'user_id',

    ];

    protected $casts = [
        'attendees' => 'array',
        'date' => 'date',
        'time' => 'datetime:H:i',
    ];


    public function user()
{
    return $this->belongsTo(User::class);
}
}
