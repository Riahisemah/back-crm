<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailCampaign;
use App\Models\EmailCampaign;
use Illuminate\Http\Request;
use Carbon\Carbon;

class EmailCampaignController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       $validatedData = $request->validate([
    'name' => 'required|string|max:255',
    'subject' => 'required|string|max:255',
    'audience' => 'required|array',
    'audience.*' => 'required|exists:users,id',
    'content' => 'required|string',
    'schedule' => 'required|in:now,later',
    'schedule_time' => 'required_if:schedule,later|nullable|date|after:now',
    'sender' => 'required|email', // add this
]);


        $campaign = EmailCampaign::create($validatedData);

        if ($campaign->schedule === 'now') {
            SendEmailCampaign::dispatch($campaign);
        } else {
            $delay = Carbon::parse($campaign->schedule_time)->diffInSeconds(now());
            SendEmailCampaign::dispatch($campaign)->delay($delay);
        }

        return response()->json($campaign, 201);
    }
}
