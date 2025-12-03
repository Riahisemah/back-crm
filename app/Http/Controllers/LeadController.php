<?php
namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Http\Request;

class LeadController extends Controller
{

    public function getByOrganisation($organisationId)
{
    $leads = Lead::where('organisation_id', $organisationId)->get();

    return response()->json([
        'status' => 'success',
        'count' => $leads->count(),
        'data' => $leads
    ]);
}

    public function filter(Request $request)
    {
        $query = Lead::query();

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
            $query->whereBetween('generated_at', [$request->from_date, $request->to_date]);
        }

        if ($request->min_followers) {
            $query->where('followers', '>=', $request->min_followers);
        }

        if ($request->min_connections) {
            $query->where('connections', '>=', $request->min_connections);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->get()
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
            'total_leads'      => 'nullable|integer'
        ]);

        $data['organisation_id'] = $organisationId;

        $lead = Lead::create($data);

        return response()->json($lead, 201);
    }

    public function show(Lead $lead)
    {
        return $lead;
    }

    public function update(Request $request, Lead $lead)
    {
        $this->authorizeOrg($request, $lead);

        $lead->update($request->all());

        return $lead;
    }

    public function destroy(Request $request, Lead $lead)
    {
        $this->authorizeOrg($request, $lead);

        $lead->delete();

        return response()->json(['success' => true]);
    }

    private function authorizeOrg(Request $request, Lead $lead)
    {
        if ($lead->organisation_id !== $request->user()->organisation_id) {
            abort(403, 'Unauthorized');
        }
    }
}
