<?php

namespace App\Http\Controllers;

use App\Models\Opportunity;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\User;

class OpportunityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Opportunity::with('organisation')->get());
    }

 public function store(Request $request)
{
    // Validate all fields including organisation_id and created_by
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'company' => 'required|string|max:255',
        'value' => 'required|numeric',
        'stage' => 'required|string|max:255',
        'probability' => 'required|integer|min:0|max:100',
        'close_date' => 'required|date',
        'contact' => 'required|string|max:255',
        'description' => 'nullable|string',
        'organisation_id' => 'required|exists:organisations,id',
        'created_by' => 'required|exists:users,id',
    ]);

    $opportunity = Opportunity::create($validated);

    return response()->json([
        'message' => 'Opportunity created successfully.',
        'opportunity' => $opportunity
    ], 201);
}



    public function show(Opportunity $opportunity): JsonResponse
    {
        return response()->json($opportunity->load('organisation'));
    }

    public function update(Request $request, Opportunity $opportunity): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'company' => 'sometimes|string|max:255',
            'value' => 'sometimes|numeric',
            'stage' => 'sometimes|string|max:255',
            'probability' => 'sometimes|integer|between:0,100',
            'close_date' => 'sometimes|date',
            'contact' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $opportunity->update($validated);
        return response()->json($opportunity);
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        $opportunity->delete();
        return response()->json(null, 204);
    }

    public function getByCreator(User $user): JsonResponse
{
    $opportunities = $user->createdOpportunities()->get();

    return response()->json($opportunities);
}

    public function getByOrganisation(Organisation $organisation): JsonResponse
    {
        return response()->json($organisation->opportunities);
    }


}
