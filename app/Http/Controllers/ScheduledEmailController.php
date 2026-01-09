<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\ScheduledEmail;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Jobs\ProcessEmailCampaign;
use App\Jobs\SendScheduledEmail;

class ScheduledEmailController extends Controller
{
    /**
     * Schedule a single email
     */
    public function scheduleSingleEmail(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), [
            'to_email' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'send_at' => 'required|date|after:now',
            'lead_id' => 'nullable|exists:leads,id',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create scheduled email
            $scheduledEmail = ScheduledEmail::create([
                'user_id' => $user->id,
                'organisation_id' => $user->organisation_id,
                'to_email' => $request->to_email,
                'subject' => $request->subject,
                'body' => $request->body,
                'status' => 'scheduled',
                'scheduled_for' => Carbon::parse($request->send_at),
                'metadata' => array_merge($request->metadata ?? [], [
                    'lead_id' => $request->lead_id,
                    'is_single_email' => true,
                ]),
            ]);

            // Dispatch job for sending
            \App\Jobs\SendScheduledEmail::dispatch($scheduledEmail)
                ->delay($scheduledEmail->scheduled_for);

            Log::info('Single email scheduled', [
                'email_id' => $scheduledEmail->id,
                'user_id' => $user->id,
                'scheduled_for' => $scheduledEmail->scheduled_for,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email scheduled successfully',
                'data' => $scheduledEmail->load('user')
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error scheduling single email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule bulk emails from leads
     */
/**
 * Schedule bulk emails from leads
 */
/**
 * Schedule bulk emails from leads
 */
public function scheduleBulkEmails(Request $request)
{
    $user = $request->user();
    
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Remove 'default' from validation rules and handle defaults manually
    $validator = Validator::make($request->all(), [
        'lead_ids' => 'required|array|min:1',
        'lead_ids.*' => 'exists:leads,id',
        'subject' => 'required|string|max:255',
        'body' => 'required|string',
        'send_at' => 'required|date|after:now',
        'personalize' => 'boolean',
        'batch_size' => 'integer|min:1|max:50',
        'create_campaign' => 'boolean',
        'campaign_name' => 'required_if:create_campaign,true|string|max:255',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Get leads
        $leads = Lead::whereIn('id', $request->lead_ids)
            ->where('organisation_id', $user->organisation_id)
            ->whereNotNull('email')
            ->get();

        if ($leads->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No valid leads found with email addresses'
            ], 400);
        }

        // Set default values if not provided
        $sendAt = Carbon::parse($request->send_at);
        $personalize = $request->has('personalize') ? (bool) $request->personalize : true;
        $batchSize = $request->has('batch_size') ? (int) $request->batch_size : 10;
        $createCampaign = $request->has('create_campaign') ? (bool) $request->create_campaign : true;

        // Prepare audience data
        $audience = $leads->map(function ($lead) {
            return [
                'id' => $lead->id,
                'email' => $lead->email,
                'name' => $lead->full_name,
                'full_name' => $lead->full_name,
                'company' => $lead->company,
                'position' => $lead->position,
                'location' => $lead->location,
            ];
        })->toArray();

        $scheduledEmails = [];

        if ($createCampaign) {
            // Validate campaign name if creating campaign
            if (!$request->has('campaign_name') || empty($request->campaign_name)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign name is required when create_campaign is true'
                ], 422);
            }

            // Create campaign - FIXED: Use total_count instead of total_recipients
            $campaign = EmailCampaign::create([
                'name' => $request->campaign_name,
                'subject' => $request->subject,
                'audience' => $audience,
                'content' => $request->body,
                'schedule' => 'later',
                'schedule_time' => $sendAt,
                'sender' => $user->id,
                'status' => 'scheduled',
                'total_count' => count($leads), // FIXED HERE
            ]);

            // Process campaign (this will create scheduled emails and dispatch jobs)
            \App\Jobs\ProcessEmailCampaign::dispatch($campaign)
                ->delay($sendAt);

            $message = 'Campaign created and scheduled successfully';
            $data = [
                'campaign' => $campaign->load('sender'),
                'total_leads' => count($leads),
                'scheduled_for' => $sendAt,
            ];

        } else {
            // Schedule emails individually without campaign
            $batchDelay = 0;
            
            foreach ($leads->chunk($batchSize) as $leadBatch) {
                foreach ($leadBatch as $lead) {
                    // Personalize content
                    $subject = $personalize ? $this->personalizeSubject($request->subject, $lead) : $request->subject;
                    $body = $personalize ? $this->personalizeContent($request->body, $lead) : $request->body;

                    // Schedule email with batch delay
                    $scheduledFor = $sendAt->copy()->addMinutes($batchDelay);
                    
                    $scheduledEmail = ScheduledEmail::create([
                        'user_id' => $user->id,
                        'organisation_id' => $user->organisation_id,
                        'to_email' => $lead->email,
                        'subject' => $subject,
                        'body' => $body,
                        'status' => 'scheduled',
                        'scheduled_for' => $scheduledFor,
                        'metadata' => [
                            'lead_id' => $lead->id,
                            'is_bulk_email' => true,
                            'personalized' => $personalize,
                        ],
                    ]);

                    // Dispatch job
                    \App\Jobs\SendScheduledEmail::dispatch($scheduledEmail)
                        ->delay($scheduledFor);

                    $scheduledEmails[] = $scheduledEmail;
                }
                $batchDelay++;
            }

            $message = 'Bulk emails scheduled successfully';
            $data = [
                'scheduled_emails' => $scheduledEmails,
                'total_leads' => count($leads),
                'scheduled_for' => $sendAt,
                'batch_size' => $batchSize,
            ];
        }

        Log::info('Bulk emails scheduled', [
            'user_id' => $user->id,
            'total_leads' => count($leads),
            'create_campaign' => $createCampaign,
            'scheduled_for' => $sendAt,
        ]);

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);

    } catch (\Exception $e) {
        Log::error('Error scheduling bulk emails', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Failed to schedule bulk emails: ' . $e->getMessage()
        ], 500);
    }
}
    /**
     * Cancel a scheduled email
     */
    public function cancelScheduledEmail(ScheduledEmail $scheduledEmail, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($scheduledEmail->user_id !== $user->id && $scheduledEmail->organisation_id !== $user->organisation_id) {
            return response()->json(['message' => 'Unauthorized for this email'], 403);
        }

        try {
            $cancelled = $scheduledEmail->cancel();

            if ($cancelled) {
                Log::info('Scheduled email cancelled', [
                    'email_id' => $scheduledEmail->id,
                    'user_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Email cancelled successfully',
                    'data' => $scheduledEmail->fresh()
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Email cannot be cancelled (may already be sent or in progress)'
            ], 400);

        } catch (\Exception $e) {
            Log::error('Error cancelling scheduled email', [
                'email_id' => $scheduledEmail->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel an entire campaign
     */
    public function cancelCampaign(EmailCampaign $campaign, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($campaign->sender !== $user->id) {
            // Check if user is in same organisation
            $sender = User::find($campaign->sender);
            if (!$sender || $sender->organisation_id !== $user->organisation_id) {
                return response()->json(['message' => 'Unauthorized for this campaign'], 403);
            }
        }

        try {
            if (!$campaign->canBeCancelled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Campaign cannot be cancelled (may already be completed or cancelled)'
                ], 400);
            }

            // Cancel all pending scheduled emails
            $pendingEmails = $campaign->scheduledEmails()
                ->whereIn('status', ['pending', 'scheduled'])
                ->get();

            $cancelledCount = 0;
            foreach ($pendingEmails as $email) {
                if ($email->cancel()) {
                    $cancelledCount++;
                }
            }

            // Update campaign status
            $campaign->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'last_processed_at' => now(),
            ]);

            Log::info('Campaign cancelled', [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'cancelled_emails' => $cancelledCount,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign cancelled successfully',
                'data' => [
                    'campaign' => $campaign->fresh(),
                    'cancelled_emails' => $cancelledCount,
                    'total_pending' => $pendingEmails->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error cancelling campaign', [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a scheduled email
     */
    public function updateScheduledEmail(ScheduledEmail $scheduledEmail, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($scheduledEmail->user_id !== $user->id && $scheduledEmail->organisation_id !== $user->organisation_id) {
            return response()->json(['message' => 'Unauthorized for this email'], 403);
        }

        // Check if email can be updated
        if (!in_array($scheduledEmail->status, ['pending', 'scheduled'])) {
            return response()->json([
                'success' => false,
                'message' => 'Email cannot be updated (may already be sent or in progress)'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'send_at' => 'nullable|date|after:now',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updates = [];
            
            if ($request->has('subject')) {
                $updates['subject'] = $request->subject;
            }
            
            if ($request->has('body')) {
                $updates['body'] = $request->body;
            }
            
            if ($request->has('send_at')) {
                $updates['scheduled_for'] = Carbon::parse($request->send_at);
            }
            
            if ($request->has('metadata')) {
                $currentMetadata = $scheduledEmail->metadata ?? [];
                $updates['metadata'] = array_merge($currentMetadata, $request->metadata);
            }

            $scheduledEmail->update($updates);

            Log::info('Scheduled email updated', [
                'email_id' => $scheduledEmail->id,
                'user_id' => $user->id,
                'updates' => array_keys($updates),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'data' => $scheduledEmail->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating scheduled email', [
                'email_id' => $scheduledEmail->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a campaign
     */
    public function updateCampaign(EmailCampaign $campaign, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($campaign->sender !== $user->id) {
            $sender = User::find($campaign->sender);
            if (!$sender || $sender->organisation_id !== $user->organisation_id) {
                return response()->json(['message' => 'Unauthorized for this campaign'], 403);
            }
        }

        if (!$campaign->canBeUpdated()) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign cannot be updated (may already be sending or completed)'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'audience' => 'nullable|array',
            'content' => 'nullable|string',
            'schedule_time' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updates = [];
            
            if ($request->has('name')) {
                $updates['name'] = $request->name;
            }
            
            if ($request->has('subject')) {
                $updates['subject'] = $request->subject;
            }
            
            if ($request->has('audience')) {
                $updates['audience'] = $request->audience;
$updates['total_count'] = count($request->audience);
            }
            
            if ($request->has('content')) {
                $updates['content'] = $request->content;
            }
            
            if ($request->has('schedule_time')) {
                $updates['schedule_time'] = Carbon::parse($request->schedule_time);
                $updates['schedule'] = 'later';
            }

            $campaign->update($updates);

            // If schedule time changed, cancel existing scheduled emails and reschedule
            if ($request->has('schedule_time') || $request->has('audience')) {
                // Cancel existing pending emails
                $campaign->scheduledEmails()
                    ->whereIn('status', ['pending', 'scheduled'])
                    ->update(['status' => 'cancelled']);

                // Schedule new campaign processing
                \App\Jobs\ProcessEmailCampaign::dispatch($campaign->fresh())
                    ->delay($campaign->schedule_time ?? now());
            }

            Log::info('Campaign updated', [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'updates' => array_keys($updates),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Campaign updated successfully',
                'data' => $campaign->fresh()->load('sender')
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating campaign', [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update campaign: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scheduled emails
     */
    public function getScheduledEmails(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $query = ScheduledEmail::where('user_id', $user->id)
                ->orWhere('organisation_id', $user->organisation_id)
                ->with(['user', 'campaign']);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('scheduled_for', '>=', Carbon::parse($request->from_date));
            }
            
            if ($request->has('to_date')) {
                $query->where('scheduled_for', '<=', Carbon::parse($request->to_date));
            }

            // Filter by campaign
            if ($request->has('campaign_id')) {
                $query->where('campaign_id', $request->campaign_id);
            }

            // Pagination
            $perPage = $request->per_page ?? 20;
            $emails = $query->orderBy('scheduled_for', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $emails
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching scheduled emails', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch scheduled emails: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaigns
     */
    public function getCampaigns(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $query = EmailCampaign::where('sender', $user->id)
                ->orWhereHas('sender', function ($q) use ($user) {
                    $q->where('organisation_id', $user->organisation_id);
                })
                ->with(['sender', 'scheduledEmails'])
                ->withCount(['scheduledEmails as sent_count' => function ($q) {
                    $q->where('status', 'sent');
                }, 'scheduledEmails as failed_count' => function ($q) {
                    $q->where('status', 'failed');
                }]);

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by date range
            if ($request->has('from_date')) {
                $query->where('schedule_time', '>=', Carbon::parse($request->from_date));
            }
            
            if ($request->has('to_date')) {
                $query->where('schedule_time', '<=', Carbon::parse($request->to_date));
            }

            // Pagination
            $perPage = $request->per_page ?? 20;
            $campaigns = $query->orderBy('schedule_time', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $campaigns
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching campaigns', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaigns: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign details
     */
    public function getCampaignDetails(EmailCampaign $campaign, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($campaign->sender !== $user->id) {
            $sender = User::find($campaign->sender);
            if (!$sender || $sender->organisation_id !== $user->organisation_id) {
                return response()->json(['message' => 'Unauthorized for this campaign'], 403);
            }
        }

        try {
            $campaign->load(['sender', 'scheduledEmails.user']);
            
            // Get statistics
            $stats = [
                'total' => $campaign->scheduledEmails->count(),
                'sent' => $campaign->scheduledEmails->where('status', 'sent')->count(),
                'failed' => $campaign->scheduledEmails->where('status', 'failed')->count(),
                'pending' => $campaign->scheduledEmails->whereIn('status', ['pending', 'scheduled'])->count(),
                'cancelled' => $campaign->scheduledEmails->where('status', 'cancelled')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'campaign' => $campaign,
                    'statistics' => $stats,
                    'audience_count' => count($campaign->audience ?? []),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching campaign details', [
                'campaign_id' => $campaign->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch campaign details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get scheduled email details
     */
    public function getScheduledEmailDetails(ScheduledEmail $scheduledEmail, Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Check permission
        if ($scheduledEmail->user_id !== $user->id && $scheduledEmail->organisation_id !== $user->organisation_id) {
            return response()->json(['message' => 'Unauthorized for this email'], 403);
        }

        try {
            $scheduledEmail->load(['user', 'campaign.sender']);
            
            // Get lead information if available
            $lead = null;
            if ($scheduledEmail->metadata && isset($scheduledEmail->metadata['lead_id'])) {
                $lead = Lead::find($scheduledEmail->metadata['lead_id']);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'email' => $scheduledEmail,
                    'lead' => $lead,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching scheduled email details', [
                'email_id' => $scheduledEmail->id,
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch email details: ' . $e->getMessage()
            ], 500);
        }
    }

    private function personalizeSubject(string $subject, Lead $lead): string
    {
        $replacements = [
            '{{lead_name}}' => $lead->full_name,
            '{{first_name}}' => explode(' ', $lead->full_name)[0] ?? $lead->full_name,
            '{{company}}' => $lead->company ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $subject);
    }

    private function personalizeContent(string $content, Lead $lead): string
    {
        $replacements = [
            '{{lead_name}}' => $lead->full_name,
            '{{first_name}}' => explode(' ', $lead->full_name)[0] ?? $lead->full_name,
            '{{company}}' => $lead->company ?? '',
            '{{position}}' => $lead->position ?? '',
            '{{location}}' => $lead->location ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}