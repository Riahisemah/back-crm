<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
  protected $fillable = [
        'organisation_id',
        'name',
        'email',
        'phone',
        'company',
        'position',
        'location',
        'status'
    ];

 public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
    
}
