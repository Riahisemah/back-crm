<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function filter(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $query = Lead::where('organisation_id', $organisationId);

        if ($request->full_name) {
            $query->where('full_name', 'LIKE', "%{$request->full_name}%");
        }

        if ($request->email) {
            $query->where('email', 'LIKE', "%{$request->email}%");
        }

        if ($request->location) {
            $query->where('location', 'LIKE', "%{$request->location}%");
        }

        if ($request->company) {
            $query->where('company', 'LIKE', "%{$request->company}%");
        }

        if ($request->position) {
            $query->where('position', 'LIKE', "%{$request->position}%");
        }

        if ($request->from_date && $request->to_date) {
            $query->whereBetween('generated_at', [
                $request->from_date,
                $request->to_date
            ]);
        }

        if ($request->min_followers) {
            $query->where('followers', '>=', $request->min_followers);
        }

        if ($request->min_connections) {
            $query->where('connections', '>=', $request->min_connections);
        }

        return response()->json([
            'status' => 'success',
            'count'  => $query->count(),
            'data'   => $query->orderBy('created_at', 'desc')->get()
        ]);
    }

    public function index(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        return Lead::where('organisation_id', $organisationId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function store(Request $request)
    {
        $organisationId = $request->user()->organisation_id;

        $data = $request->validate([
            'full_name'        => 'required|string|max:255',
            'email'            => 'nullable|email|max:255',
            'position'         => 'nullable|string|max:255',
            'company'          => 'nullable|string|max:255',
            'location'         => 'nullable|string|max:255',
            'profile_url'      => 'nullable|url',
            'followers'        => 'nullable|integer',
            'connections'      => 'nullable|integer',
            'education'        => 'nullable|string|max:255',
            'personal_message' => 'nullable|string',
            'message_length'   => 'nullable|integer',
            'generated_at'     => 'nullable|date',
            'total_leads'      => 'nullable|integer',
            'comments'         => 'nullable|string',
            'status'           => 'nullable|in:to_be_treated,qualified,archived',
        ]);

        $data['organisation_id'] = $organisationId;

        $lead = Lead::create($data);

        return response()->json($lead, 201);
    }

    public function show(Request $request, Lead $lead)
    {
        $this->authorizeOrg($request, $lead);

        return $lead;
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeOrg($request, $lead);

        $data = $request->validate([
            'full_name'        => 'sometimes|string|max:255',
            'email'            => 'sometimes|nullable|email|max:255',
            'position'         => 'sometimes|nullable|string|max:255',
            'company'          => 'sometimes|nullable|string|max:255',
            'location'         => 'sometimes|nullable|string|max:255',
            'profile_url'      => 'sometimes|nullable|url',
            'followers'        => 'sometimes|nullable|integer',
            'connections'      => 'sometimes|nullable|integer',
            'education'        => 'sometimes|nullable|string|max:255',
            'personal_message' => 'sometimes|nullable|string',
            'message_length'   => 'sometimes|nullable|integer',
            'generated_at'     => 'sometimes|nullable|date',
            'total_leads'      => 'sometimes|nullable|integer',
            'comments'         => 'sometimes|nullable|string',
            'status'           => 'sometimes|in:to_be_treated,qualified,archived',
        ]);

        $lead->update($data);

        return $lead;
    }

    public function destroy(Request $request, Lead $lead)
    {
        $this->authorizeOrg($request, $lead);

        $lead->delete();

        return response()->json(['success' => true]);
    }

    public function getByOrganisation(Request $request, $organisationId)
    {
        if ($request->user()->organisation_id !== (int) $organisationId) {
            abort(403, 'Unauthorized');
        }

        $leads = Lead::where('organisation_id', $organisationId)->get();

        return response()->json([
            'status' => 'success',
            'count'  => $leads->count(),
            'data'   => $leads
        ]);
    }

    private function authorizeOrg(Request $request, Lead $lead)
    {
        if ($lead->organisation_id !== $request->user()->organisation_id) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Replace old "treated" logic
     * Mark as QUALIFIED
     */
   /**
 * Mark a lead as treated (boolean flag)
 */
public function markAsTreated(Request $request, Lead $lead)
{
    $this->authorizeOrg($request, $lead);

    $lead->update([
        'treated' => true,
    ]);

    return response()->json([
        'status' => 'success',
        'lead'   => $lead,
    ]);
}

/**
 * Optional: mark as untreated
 */
public function markAsUntreated(Request $request, Lead $lead)
{
    $this->authorizeOrg($request, $lead);

    $lead->update([
        'treated' => false,
    ]);

    return response()->json([
        'status' => 'success',
        'lead'   => $lead,
    ]);
}

}
