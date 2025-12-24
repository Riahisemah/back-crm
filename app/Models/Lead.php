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
    'organisation_id'
];


    protected $dates = ['generated_at'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
}
