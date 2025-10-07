<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Appointment; 

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
   protected $fillable = ['name', 'email', 'password', 'organisation_id',     'first_time_login',
];

public function organisation() {
    return $this->belongsTo(Organisation::class);
}

public function appointments()
{
    return $this->hasMany(Appointment::class);
}

public function tasks() {
    return $this->hasMany(Task::class, 'assignee_id');
}

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    public function createdOpportunities()
{
    return $this->hasMany(Opportunity::class, 'created_by');
}

public function notifications()
{
    return $this->hasMany(\App\Models\UserNotification::class, 'user_id')->orderByDesc('timestamp');
}


}
