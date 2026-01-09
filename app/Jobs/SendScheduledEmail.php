<?php

namespace App\Jobs;

use App\Models\ScheduledEmail;
use App\Services\GoogleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendScheduledEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 120;
    public $backoff = [60, 300, 600];

    protected $scheduledEmail;

    public function __construct(ScheduledEmail $scheduledEmail)
    {
        $this->scheduledEmail = $scheduledEmail->withoutRelations();
    }

    public function handle(GoogleService $googleService): void
    {
        try {
            // Reload the email to get fresh data
            $email = ScheduledEmail::find($this->scheduledEmail->id);
            
            if (!$email || !$email->canBeSentNow()) {
                Log::warning('Scheduled email cannot be sent or no longer exists', [
                    'email_id' => $this->scheduledEmail->id,
                    'status' => $email ? $email->status : 'deleted'
                ]);
                return;
            }

            // Mark as processing
            $email->markAsProcessing();

            // Send email using Google Service
            $client = $googleService->getAuthenticatedClient($email->user_id);
            
            if (!$client) {
                throw new \Exception('Failed to authenticate with Google. Please reconnect your Google account.');
            }

            $gmail = new \Google\Service\Gmail($client);
            $message = new \Google\Service\Gmail\Message();

            // Prepare email headers
            $headers = [
                'From: ' . $this->getSenderEmail($email->user_id),
                'To: ' . $email->to_email,
                'Subject: ' . $email->subject,
                'Content-Type: text/html; charset=utf-8',
            ];

            $rawMessage = implode("\r\n", $headers) . "\r\n\r\n";
            $rawMessage .= $email->body;

            $message->setRaw(base64_encode($rawMessage));
            $result = $gmail->users_messages->send('me', $message);

            // Mark as sent
            $email->markAsSent($result->getId());

            // Log to email_logs table
            $this->logToEmailLogs($email, $result->getId());

            // Update campaign stats - FIXED: Load campaign relationship first
            if ($email->campaign_id) {
                // Reload the email with campaign relationship
                $email->load('campaign');
                if ($email->campaign) {
                    $email->campaign->updateStats();
                }
            }

            Log::info('Scheduled email sent successfully', [
                'email_id' => $email->id,
                'campaign_id' => $email->campaign_id,
                'to' => $email->to_email,
                'message_id' => $result->getId()
            ]);

        } catch (\Google\Service\Exception $e) {
            Log::error('Google API error sending scheduled email', [
                'email_id' => $this->scheduledEmail->id,
                'error' => $e->getMessage(),
                'details' => $e->getErrors() ?? []
            ]);

            $this->handleFailure($email ?? null, 'Google API error: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('Error sending scheduled email', [
                'email_id' => $this->scheduledEmail->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->handleFailure($email ?? null, 'Failed to send email: ' . $e->getMessage());
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendScheduledEmail job failed', [
            'email_id' => $this->scheduledEmail->id,
            'error' => $exception->getMessage(),
            'job_attempts' => $this->attempts()
        ]);

        $email = ScheduledEmail::find($this->scheduledEmail->id);
        if ($email) {
            $this->handleFailure($email, 'Job failed: ' . $exception->getMessage());
        }
    }

    private function handleFailure(?ScheduledEmail $email, string $error): void
    {
        if ($email) {
            $email->markAsFailed($error);
            
            // Update campaign stats
            if ($email->campaign_id) {
                $email->load('campaign');
                if ($email->campaign) {
                    $email->campaign->updateStats();
                }
            }
        }
    }

    private function getSenderEmail($userId): string
    {
        $provider = \App\Models\EmailProvider::where('user_id', $userId)
            ->where('provider', 'google')
            ->first();

        return $provider ? $provider->provider_email : config('mail.from.address');
    }

    private function logToEmailLogs(ScheduledEmail $email, string $messageId): void
    {
        try {
            \App\Models\EmailLog::create([
                'lead_id' => $email->metadata['lead_id'] ?? null,
                'user_id' => $email->user_id,
                'organisation_id' => $email->organisation_id,
                'to_email' => $email->to_email,
                'subject' => $email->subject,
                'body' => $email->body,
                'message_id' => $messageId,
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log to email_logs', [
                'email_id' => $email->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}