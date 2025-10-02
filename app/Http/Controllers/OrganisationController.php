<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganisationController extends Controller
{
        // GET /organisations
      public function index(): Response
    {
        $organisations = Organisation::all();
        return response($organisations, 200);
    }

     // POST /organisations
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);

        $organisation = Organisation::create($validated);
        return response($organisation, 201);
    }

     // GET /organisations/{id}
    public function show(Organisation $organisation): Response
    {
        return response($organisation, 200);
    }
    
   // PUT /organisations/{id}
    public function update(Request $request, Organisation $organisation): Response
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email',
        ]);

        $organisation->update($validated);
        return response($organisation, 200);
    }

    // DELETE /organisations/{id}
    public function destroy(Organisation $organisation): Response
    {
        $organisation->delete();
        return response(null, 204);
    }
}
