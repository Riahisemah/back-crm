<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

 class Invitation extends Model
{
    protected $fillable = ['organisation_id', 'token', 'accepted', 'inviter_id', 'invitee_id'];

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }


}
