<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Invitation;
use App\Models\User;
use App\Models\Organisation;
use Illuminate\Support\Str;

class InvitationController extends Controller
{
    public function sendInvite(Request $request, Organisation $organisation)
    {
        $request->validate([
            'invitee_id' => 'required|exists:users,id',
        ]);

        $invitee = User::findOrFail($request->invitee_id);

        if ($invitee->organisation_id === $organisation->id) {
            return response()->json(['error' => 'User already belongs to this organisation.'], 422);
        }

        $token = Str::random(40);

        $invitation = Invitation::create([
            'organisation_id' => $organisation->id,
            'token' => $token,
            'inviter_id' => auth()->id(),
            'invitee_id' => $invitee->id,
            'accepted' => false,
        ]);

        return response()->json(['message' => 'Invitation sent', 'token' => $token], 201);
    }

    public function acceptInvite(Request $request)
    {
        $request->validate([
            'token' => 'required',
        ]);

        $invitation = Invitation::where('token', $request->token)
            ->where('accepted', false)
            ->first();

        if (!$invitation) {
            return response()->json(['error' => 'Invalid or expired invitation.'], 404);
        }

        $user = auth()->user();

        if ($user->id !== $invitation->invitee_id) {
            return response()->json(['error' => 'You are not authorized to accept this invitation.'], 403);
        }

        $user->organisation_id = $invitation->organisation_id;
        $user->first_time_login = true;
        $user->save();

        $invitation->accepted = true;
        $invitation->save();

        return response()->json(['message' => 'Invitation accepted.', 'user' => $user]);
    }
}
