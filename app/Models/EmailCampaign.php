<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'audience',
        'content',
        'schedule',
        'schedule_time',
        'sender', // add this

    ];

    protected $casts = [
        'audience' => 'array',
        'schedule_time' => 'datetime',
    ];

    /**
     * Get the user that owns the campaign.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender');
    }
}
