<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AppointmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Appointment::all());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'required|date',
            'time' => 'required|date_format:H:i',
            'duration' => 'required|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'location' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => 'string',
            'related_to' => 'nullable|string',
            'user_id' => 'required|exists:users,id',

        ]);

        $appointment = Appointment::create($validated);

        return response()->json($appointment, 201);
    }

    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json($appointment);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'date' => 'sometimes|date',
            'time' => 'sometimes|date_format:H:i',
            'duration' => 'sometimes|string',
            'type' => 'nullable|string',
            'status' => 'nullable|string',
            'location' => 'nullable|string',
            'attendees' => 'nullable|array',
            'attendees.*' => 'string',
            'related_to' => 'nullable|string',
            'user_id' => 'sometimes|exists:users,id',

        ]);

        $appointment->update($validated);

        return response()->json($appointment);
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $appointment->delete();

        return response()->json(['message' => 'Appointment deleted successfully.']);
    }
}
