<?php

namespace App\Jobs;

use App\Models\EmailCampaign;
use App\Models\ScheduledEmail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 300;

    protected $campaign;

    public function __construct(EmailCampaign $campaign)
    {
        $this->campaign = $campaign->withoutRelations();
    }

    public function handle(): void
    {
        try {
            // Reload campaign
            $campaign = EmailCampaign::find($this->campaign->id);
            
            if (!$campaign || $campaign->status !== 'scheduled') {
                Log::warning('Campaign cannot be processed or no longer scheduled', [
                    'campaign_id' => $this->campaign->id,
                    'status' => $campaign ? $campaign->status : 'deleted'
                ]);
                return;
            }

            // Update campaign status to sending
            $campaign->update([
                'status' => 'sending',
                'started_at' => now(),
                'last_processed_at' => now(),
            ]);

            // Parse audience
            $audience = $campaign->audience ?? [];
            $totalRecipients = count($audience);
            
            if ($totalRecipients === 0) {
                Log::warning('Campaign has no recipients', ['campaign_id' => $campaign->id]);
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'total_count' => 0,
                ]);
                return;
            }

            // Create scheduled emails for each recipient
            $sender = User::find($campaign->sender);
            if (!$sender) {
                throw new \Exception('Sender not found');
            }

            $delayMinutes = 0;
            $batchSize = 10; // Send 10 emails per minute to avoid rate limiting
            
            foreach ($audience as $index => $recipientData) {
                // Determine recipient email
                $recipientEmail = $this->getRecipientEmail($recipientData);
                if (!$recipientEmail) {
                    continue;
                }

                // Calculate scheduled time (stagger emails)
                $batchIndex = floor($index / $batchSize);
                $scheduledFor = now()->addMinutes($batchIndex);
                
                if ($campaign->schedule_time && $campaign->schedule_time->gt($scheduledFor)) {
                    $scheduledFor = $campaign->schedule_time->copy()->addMinutes($batchIndex);
                }

                // Create scheduled email
                $scheduledEmail = ScheduledEmail::create([
                    'campaign_id' => $campaign->id,
                    'user_id' => $campaign->sender,
                    'organisation_id' => $sender->organisation_id,
                    'to_email' => $recipientEmail,
                    'subject' => $this->personalizeSubject($campaign->subject, $recipientData),
                    'body' => $this->personalizeContent($campaign->content, $recipientData),
                    'status' => 'scheduled',
                    'scheduled_for' => $scheduledFor,
                    'metadata' => [
                        'recipient_data' => $recipientData,
                        'campaign_name' => $campaign->name,
                        'lead_id' => $recipientData['id'] ?? null,
                    ],
                ]);

                // CRITICAL FIX: Dispatch SendScheduledEmail job
                SendScheduledEmail::dispatch($scheduledEmail)
                    ->delay($scheduledFor);

                Log::debug('Created and dispatched scheduled email', [
                    'campaign_id' => $campaign->id,
                    'email_id' => $scheduledEmail->id,
                    'to' => $recipientEmail,
                    'scheduled_for' => $scheduledFor,
                ]);
            }

            // Update campaign with total recipients count
            $campaign->update([
                'total_count' => $totalRecipients,
                'last_processed_at' => now(),
            ]);

            Log::info('Campaign processing completed', [
                'campaign_id' => $campaign->id,
                'total_recipients' => $totalRecipients,
                'sender_id' => $campaign->sender,
                'scheduled_emails_created' => $totalRecipients,
            ]);

        } catch (\Exception $e) {
            Log::error('Error processing email campaign', [
                'campaign_id' => $this->campaign->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $campaign = EmailCampaign::find($this->campaign->id);
            if ($campaign) {
                $campaign->update([
                    'status' => 'failed',
                    'last_processed_at' => now(),
                ]);
            }
        }
    }

    private function getRecipientEmail($recipientData): ?string
    {
        if (is_array($recipientData)) {
            return $recipientData['email'] ?? null;
        } elseif (is_string($recipientData)) {
            return filter_var($recipientData, FILTER_VALIDATE_EMAIL) ? $recipientData : null;
        }
        
        return null;
    }

    private function personalizeSubject(string $subject, $recipientData): string
    {
        if (!is_array($recipientData)) {
            return $subject;
        }

        $replacements = [
            '{{lead_name}}' => $recipientData['name'] ?? $recipientData['full_name'] ?? '',
            '{{first_name}}' => explode(' ', $recipientData['name'] ?? $recipientData['full_name'] ?? '')[0] ?? '',
            '{{company}}' => $recipientData['company'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $subject);
    }

    private function personalizeContent(string $content, $recipientData): string
    {
        if (!is_array($recipientData)) {
            return $content;
        }

        $replacements = [
            '{{lead_name}}' => $recipientData['name'] ?? $recipientData['full_name'] ?? '',
            '{{first_name}}' => explode(' ', $recipientData['name'] ?? $recipientData['full_name'] ?? '')[0] ?? '',
            '{{company}}' => $recipientData['company'] ?? '',
            '{{position}}' => $recipientData['position'] ?? '',
            '{{location}}' => $recipientData['location'] ?? '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }
}