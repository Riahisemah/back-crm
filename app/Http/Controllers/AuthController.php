<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;  
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;




class AuthController extends Controller
{

public function signup(Request $request)
{
    try {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'organisation_id' => 'nullable|exists:organisations,id',
        ]);
    } catch (ValidationException $e) {
        // Return errors as JSON with 422 Unprocessable Entity
        return response()->json([
            'errors' => $e->errors()
        ], 422);
    }

    $validated['password'] = Hash::make($validated['password']);
    $user = User::create($validated);
    $token = $user->createToken('api_token')->plainTextToken;

    return response(['user' => $user, 'token' => $token], 201);
}


public function login(Request $request): JsonResponse
{
    // Validate input
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    // Attempt to find user
    $user = User::where('email', $validated['email'])->first();

    // Check password and user existence with consistent error message
    if (!$user || !Hash::check($validated['password'], $user->password)) {
        // Use generic message for security reasons
        return response()->json([
            'message' => 'Invalid credentials',
        ], 401);
    }

    // Save the original first_time_login value to return before update
    $firstTimeLogin = $user->first_time_login;

    // If it's the first login, update the flag to false
    if ($firstTimeLogin) {
        $user->update(['first_time_login' => false]);
    }

    // Create access token
    $token = $user->createToken('api_token')->plainTextToken;

    // Return response including first_time_login flag and tokens
  return response()->json([
    'user' => [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'organisation_id' => $user->organisation_id,
        'first_time_login' => $firstTimeLogin,
    ],
    'token' => $token,
    'first_time_login' => $firstTimeLogin,
]);
/*
{
    "user": {
        "id": 6,
        "name": "anas",
        "email": "danaffs2@test.com",
        "email_verified_at": null,
        "created_at": "2025-06-28T11:37:14.000000Z",
        "updated_at": "2025-07-09T12:17:04.000000Z",
        "organisation_id": 4,
        "first_time_login": 0,
        "refresh_token": "4be1b68309bd026f49fc7b09f657de12d253a408d128eec20aac2db5d718eede"
    },
    "token": "142|ruTo0nWNk8ZeQhz3t4576CWDEfqRUf9W0nPSDqlX2f064153",
    "refreshToken": "3kBvUcsI68JgMN2TDhNZZNx160iZR7go62IzvF0rbnWPqX0WOszXbNunombo",
    "first_time_login": 0
}
     */
}






public function logout(Request $request) {
        $request->user()->currentAccessToken()->delete();
        return response(null, 204);
    }


public function me(Request $request): JsonResponse
{
    try {
        $user = auth()->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organisation_id' => $user->organisation_id,
                'first_time_login' => $user->first_time_login,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]
        ], 200);
    } catch (\Throwable $e) {
        return response()->json(['message' => 'Invalid or missing token.'], 401);
    }
}





}
