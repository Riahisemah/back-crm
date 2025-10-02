<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    /**
     * List all tasks for the authenticated user’s organisation.
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();

        $tasks = Task::with(['assignee', 'relatedUser'])
            ->where('organisation_id', $user->organisation_id)
            ->get();

        return response()->json($tasks, 200);
    }

    /**
     * Create new task
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'assignee_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'nullable|string',
            'due_date' => 'nullable|date',
            'related_to' => 'nullable|email',
        ]);

        // Check assignee belongs to organisation
        $assignee = User::findOrFail($validated['assignee_id']);
        if ($assignee->organisation_id !== $validated['organisation_id']) {
            return response()->json(['error' => 'Assignee not in organisation'], 422);
        }

        // If related_to email is provided, try to match a user
        $relatedUser = null;
        if (!empty($validated['related_to'])) {
            $relatedUser = User::where('email', $validated['related_to'])->first();
        }

        $task = Task::create([
            ...$validated,
            'related_to_user_id' => $relatedUser?->id,
        ]);

        return response()->json($task->load(['assignee', 'relatedUser']), 201);
    }

    /**
     * Show one task (only if in same organisation as auth user)
     */
    public function show(Task $task): JsonResponse
    {
        $user = Auth::user();

        if ($task->organisation_id !== $user->organisation_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($task->load(['assignee', 'relatedUser']), 200);
    }

    /**
     * Update a task
     */
    public function update(Request $request, Task $task): JsonResponse
    {
        $user = Auth::user();

        if ($task->organisation_id !== $user->organisation_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'priority' => 'nullable|string',
            'status' => 'nullable|string',
            'due_date' => 'nullable|date',
            'related_to' => 'nullable|email',
            'assignee_id' => 'sometimes|exists:users,id',
        ]);

        if (isset($validated['assignee_id'])) {
            $assignee = User::findOrFail($validated['assignee_id']);
            if ($assignee->organisation_id !== $task->organisation_id) {
                return response()->json(['error' => 'Assignee not in organisation'], 422);
            }
        }

        // Update related user if email is given
        if (isset($validated['related_to'])) {
            $relatedUser = User::where('email', $validated['related_to'])->first();
            $validated['related_to_user_id'] = $relatedUser?->id;
        }

        $task->update($validated);

        return response()->json($task->load(['assignee', 'relatedUser']), 200);
    }

    /**
     * Delete a task
     */
    public function destroy(Task $task): Response
    {
        $user = Auth::user();

        if ($task->organisation_id !== $user->organisation_id) {
            return response(['error' => 'Unauthorized'], 403);
        }

        $task->delete();

        return response(null, 204);
    }

    /**
     * Get all tasks for a given organisation
     */
    public function getByOrganisation(Organisation $organisation): JsonResponse
    {
        $tasks = $organisation->tasks()->with(['assignee', 'relatedUser'])->get();

        return response()->json($tasks, 200);
    }

    /**
     * Get all tasks assigned to a given user
     */
    public function getByAssignee(User $user): JsonResponse
    {
        $tasks = $user->assignedTasks()->with(['organisation', 'relatedUser'])->get();

        return response()->json($tasks, 200);
    }
}
