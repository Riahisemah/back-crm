<?php
// app/Console/Commands/SendTaskReminders.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Task;
use App\Models\TaskReminder;
use App\Services\NotificationService;
use Carbon\Carbon;
use DB;

class SendTaskReminders extends Command
{
    protected $signature = 'notifications:send-task-reminders {--days=1}';
    protected $description = 'Send reminders for tasks due in X days (default: 1)';

    protected NotificationService $notify;

    public function __construct(NotificationService $notify)
    {
        parent::__construct();
        $this->notify = $notify;
    }

    public function handle()
    {
        $days = (int) $this->option('days') ?: 1;

        // find tasks with due_date between now and now + $days that have not got a reminder of this type.
        $start = Carbon::now()->startOfDay();
        $end = Carbon::now()->addDays($days)->endOfDay();

        $tasks = Task::whereNotNull('due_date')
            ->where('status', '!=', 'completed') // skip completed
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->get();

        foreach ($tasks as $task) {
            if (!$task->assignee_id) continue;

            $reminderType = "due_soon_{$days}d";

            $exists = TaskReminder::where('task_id', $task->id)
                      ->where('type', $reminderType)
                      ->exists();

            if ($exists) continue;

            DB::transaction(function() use ($task, $reminderType, $days) {
                $this->notify->createForUser($task->assignee_id, [
                    'title' => 'Task due soon',
                    'message' => "Task '{$task->title}' is due on {$task->due_date}.",
                    'type' => 'reminder',
                    'category' => 'reminder',
                    'timestamp' => Carbon::now(),
                    'action_data' => ['task_id' => $task->id, 'due_date' => $task->due_date],
                    'related_view' => ['name' => 'task', 'id' => $task->id],
                ]);

                TaskReminder::create([
                    'task_id' => $task->id,
                    'type' => $reminderType,
                    'sent_at' => Carbon::now(),
                ]);
            });
        }

        $this->info('Task reminders processed.');
    }
}
