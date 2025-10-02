<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * List all users (admin only)
     */
    public function index(): Response
    {
        $users = User::all();
        return response($users, 200);
    }

    /**
     * Get all users of a specific organisation
     */
    public function getByOrganisation(Organisation $organisation): JsonResponse
    {
        $users = $organisation->users()->get();
        return response()->json($users, 200);
    }

    /**
     * Get all users in the organisation of the logged-in user
     */
    public function getOrganisationUsers(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->organisation_id) {
            return response()->json(['error' => 'User does not belong to an organisation'], 404);
        }

        $users = User::where('organisation_id', $user->organisation_id)->get();

        return response()->json($users, 200);
    }

    /**
     * Create new user
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'organisation_id' => 'nullable|exists:organisations,id',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);

        return response($user, 201);
    }

    /**
     * Show one user
     */
    public function show(User $user): Response
    {
        return response($user, 200);
    }

    /**
     * Update user
     */
    public function update(Request $request, User $user): Response
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:6',
            'organisation_id' => 'sometimes|exists:organisations,id',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);
        return response($user, 200);
    }

    /**
     * Delete user
     */
    public function destroy(User $user): Response
    {
        $user->delete();
        return response(null, 204);
    }

    /**
     * Assign a user to an organisation
     */
    public function assignOrganisation(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'organisation_id' => 'required|exists:organisations,id',
        ]);

        $user->organisation_id = $validated['organisation_id'];
        $user->save();

        return response()->json([
            'message' => 'User assigned to new organisation successfully.',
            'user' => $user,
        ], 200);
    }

    /**
     * Add existing user to an organisation by user_id
     */
    public function addUserToOrganisation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'organisation_id' => 'required|exists:organisations,id',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $user->organisation_id = $validated['organisation_id'];
        $user->save();

        return response()->json([
            'message' => 'User added to organisation successfully.',
            'user' => $user,
        ], 200);
    }

    /**
     * Add user to organisation by email
     */
    public function addUserByEmail(Request $request, Organisation $organisation): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Check if user already belongs to this organisation
        if ($user->organisation_id === $organisation->id) {
            return response()->json([
                'message' => 'User is already in this organisation.',
                'user' => $user,
            ], 200);
        }

        // Assign user to organisation
        $user->organisation_id = $organisation->id;
        $user->save();

        return response()->json([
            'message' => 'User successfully added to the organisation.',
            'user' => $user,
        ], 200);
    }
}
