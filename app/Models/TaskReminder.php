<?php
// app/Models/TaskReminder.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskReminder extends Model
{
    protected $fillable = ['task_id','type','sent_at'];

    protected $dates = ['sent_at'];
}
