<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Task;
use App\Models\Opportunity;
use App\Models\Appointment;
use App\Models\Contact;

class DashboardController extends Controller
{


public function stats(Request $request)
{
    $user = $request->user();
    $organisation = $user->organisation;

    // Default values if no organisation
    $organisationUsers = 0;
    $opportunitiesCount = 0;
    $opportunitiesByStage = [];
    $pipelineValue = 0;
    $pendingTasksCount = 0;
    $tasksOverdue = 0;
    $upcomingTasks = [];
    $appointmentsToday = 0;
    $contactsCount = 0;
    $monthlyNewContacts = 0;
    $taskPriorities = [];

    if ($organisation) {
        // Users in the organisation
        $organisationUsers = $organisation->users()->count();

        // Opportunities
        $opportunitiesCount = $organisation->opportunities()->count();
        $opportunitiesByStage = $organisation->opportunities()
            ->selectRaw('stage, COUNT(*) as count')
            ->groupBy('stage')
            ->pluck('count', 'stage');
        $pipelineValue = $organisation->opportunities()
            ->where('stage', '!=', 'closed')
            ->sum('value');

        // Tasks
        $pendingTasksCount = $organisation->tasks()
            ->where('status', 'open')
            ->count();
        $tasksOverdue = $organisation->tasks()
            ->where('status', 'open')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();

        // Upcoming tasks with assignee
        $upcomingTasks = $organisation->tasks()
            ->where('status', 'open')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', now()->toDateString())
            ->with('assignee:id,name') // eager load assignee
            ->orderBy('due_date', 'asc')
            ->take(5)
            ->get(['id', 'title', 'due_date', 'assignee_id'])
            ->map(function ($task) {
                return [
                    'id'       => $task->id,
                    'title'    => $task->title,
                    'due_date' => $task->due_date,
                    'assignee' => $task->assignee?->name ?? 'N/A', // fallback if no assignee
                ];
            });

        $taskPriorities = $organisation->tasks()
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        // Appointments today
        $appointmentsToday = Appointment::whereIn('user_id', $organisation->users->pluck('id'))
            ->whereDate('date', now()->toDateString())
            ->count();

        // Contacts
        $contactsCount = $organisation->contacts()->count();
        $monthlyNewContacts = $organisation->contacts()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
    }

    // Return response
    return response()->json([
        'organisation_users'    => $organisationUsers,
        'opportunities_count'   => $opportunitiesCount,
        'opportunities_by_stage'=> $opportunitiesByStage,
        'pipeline_value'        => $pipelineValue,
        'pending_tasks_count'   => $pendingTasksCount,
        'tasks_overdue'         => $tasksOverdue,
        'task_priorities'       => $taskPriorities,
        'upcoming_tasks'        => $upcomingTasks,
        'appointments_today'    => $appointmentsToday,
        'contacts_count'        => $contactsCount,
        'monthly_new_contacts'  => $monthlyNewContacts,
    ]);
}


}
