<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\EmailCampaign;
use App\Models\EmailProvider;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_Message;

class SendEmailCampaign implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $campaign;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(EmailCampaign $campaign)
    {
        $this->campaign = $campaign;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $senderUser = User::find($this->campaign->sender);
        if (!$senderUser) {
            Log::error("Sender user with ID {$this->campaign->sender} not found for campaign {$this->campaign->id}");
            return;
        }

        $emailProvider = EmailProvider::where('user_id', $senderUser->id)->first();

        if ($emailProvider && $emailProvider->provider === 'google') {
            $this->sendWithGoogle($senderUser, $emailProvider);
        } else {
            $this->sendWithDefaultMailer($senderUser);
        }
    }

    protected function sendWithGoogle(User $senderUser, EmailProvider $emailProvider)
    {
        $client = new Google_Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken([
            'access_token' => $emailProvider->access_token,
            'refresh_token' => $emailProvider->refresh_token,
            'expires_in' => $emailProvider->expires_at->getTimestamp() - time(),
        ]);

        if ($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            $emailProvider->update([
                'access_token' => $client->getAccessToken()['access_token'],
                'expires_at' => now()->addSeconds($client->getAccessToken()['expires_in']),
            ]);
        }

        $gmail = new Google_Service_Gmail($client);
        $users = User::with('organisation')->find($this->campaign->audience);

        foreach ($users as $user) {
            $companyName = $user->organisation ? $user->organisation->name : '';
            $personalizedContent = str_replace(
                ['{{first_name}}', '{{company}}'],
                [$user->name, $companyName],
                $this->campaign->content
            );

            try {
                $message = new Google_Service_Gmail_Message();
                $rawMessageString = "From: {$senderUser->email}\r\n";
                $rawMessageString .= "To: {$user->email}\r\n";
                $rawMessageString .= "Subject: {$this->campaign->subject}\r\n";
                $rawMessageString .= "Content-Type: text/html; charset=utf-8\r\n\r\n";
                $rawMessageString .= $personalizedContent;
                $rawMessage = strtr(base64_encode($rawMessageString), '+/=', '-_');
                $message->setRaw($rawMessage);
                $gmail->users_messages->send('me', $message);
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$user->email} for campaign {$this->campaign->id} using Google: {$e->getMessage()}");
            }
        }
    }

    protected function sendWithDefaultMailer(User $senderUser)
    {
        $users = User::with('organisation')->find($this->campaign->audience);

        foreach ($users as $user) {
            $companyName = $user->organisation ? $user->organisation->name : '';
            $personalizedContent = str_replace(
                ['{{first_name}}', '{{company}}'],
                [$user->name, $companyName],
                $this->campaign->content
            );

            try {
                Mail::to($user->email)
                    ->replyTo($senderUser->email)
                    ->send(
                        new CampaignEmail(
                            $personalizedContent,
                            $this->campaign->subject,
                            config('mail.from.address')
                        )
                    );
            } catch (\Exception $e) {
                Log::error("Failed to send email to {$user->email} for campaign {$this->campaign->id}: {$e->getMessage()}");
            }
        }
    }
}
