<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'user_id',
        'organisation_id',
        'to_email',
        'subject',
        'body',
        'message_id',
        'status',
        'error_message',
        'sent_at',
        'scheduled_for'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'scheduled_for' => 'datetime'
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}