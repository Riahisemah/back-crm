<?php
// app/Observers/TaskObserver.php
namespace App\Observers;

use App\Models\Task;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    protected NotificationService $notify;

    public function __construct(NotificationService $notify)
    {
        $this->notify = $notify;
    }

   public function created(Task $task)
{
    // Send notification to related user instead of the creator
    $relatedUserId = $task->related_to_user_id ?? $task->assignee_id;

    if ($relatedUserId) {
        $this->notify->createForUser($relatedUserId, [
            'title' => 'New task assigned',
            'message' => "A task has been assigned to you: {$task->title}",
            'type' => 'info',
            'category' => 'task',
            'timestamp' => now(),
            'action_data' => ['task_id' => $task->id],
            'related_view' => ['name' => 'task', 'id' => $task->id],
        ]);
    }
}


   public function updated(Task $task)
{
    if ($task->isDirty('related_to_user_id')) {
        $old = $task->getOriginal('related_to_user_id');
        $new = $task->related_to_user_id;

        if ($new) {
            $this->notify->createForUser($new, [
                'title' => 'Task assigned to you',
                'message' => "A task was assigned to you: {$task->title}",
                'type' => 'info',
                'category' => 'task',
                'timestamp' => now(),
                'action_data' => ['task_id' => $task->id],
                'related_view' => ['name' => 'task', 'id' => $task->id],
            ]);
        }

        if ($old) {
            $this->notify->createForUser($old, [
                'title' => 'Task reassigned',
                'message' => "The task '{$task->title}' was reassigned.",
                'type' => 'warning',
                'category' => 'task',
                'timestamp' => now(),
                'action_data' => ['task_id' => $task->id],
            ]);
        }
    }
}

}
