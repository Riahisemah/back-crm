<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// app/Models/EmailProvider.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'access_token',
        'refresh_token',
        'expires_at',
        'provider_user_id',
        'provider_email',
        'connected',
    ];
    
    protected $casts = [
        'expires_at' => 'datetime', // This is CRITICAL
        'connected' => 'boolean',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}