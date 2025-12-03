<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany; 

class Organisation extends Model
{
  protected $fillable = ['name', 'address', 'phone', 'email'];

  
  
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
 
    public function opportunities()
{
    return $this->hasMany(Opportunity::class);
}

    public function leads()
{
    return $this->hasMany(Lead::class);
}


}
