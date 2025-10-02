<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Organisation;

class ContactController extends Controller
{
    public function index(): Response
    {
        return response(Contact::all(), 200);
    }

     public function getByOrganisation(Organisation $organisation)
    {
        return response()->json($organisation->contacts()->get());
    }

    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'organisation_id' => 'required|exists:organisations,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        $contact = Contact::create($validated);
        return response($contact, 201);
    }

    public function show(Contact $contact): Response
    {
        return response($contact, 200);
    }

    public function update(Request $request, Contact $contact): Response
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'location' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:50',
        ]);

        $contact->update($validated);
        return response($contact, 200);
    }

    public function destroy(Contact $contact): Response
    {
        $contact->delete();
        return response(null, 204);
    }
}
