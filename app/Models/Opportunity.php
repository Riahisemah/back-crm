<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'organisation_id',
        'title',
        'company',
        'value',
        'stage',
        'probability',
        'close_date',
        'contact',
        'description',
        'created_by', 

    ];
    

    public function creator()
{
    return $this->belongsTo(User::class, 'created_by');
}


    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }
    
}
