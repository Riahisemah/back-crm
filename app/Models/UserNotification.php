<?php
// app/Models/UserNotification.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $table = 'user_notifications';

    protected $fillable = [
        'user_id','title','message','type','timestamp','read',
        'avatar','related_view','action_data','category'
    ];

    protected $casts = [
        'related_view' => 'array',
        'action_data' => 'array',
        'timestamp' => 'datetime',
        'read' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
