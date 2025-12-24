<?php

namespace App\Jobs;

use App\Mail\CampaignEmail;
use App\Models\EmailCampaign;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
        $users = User::with('organisation')->find($this->campaign->audience);

        foreach ($users as $user) {
            $companyName = $user->organisation ? $user->organisation->name : '';
            $personalizedContent = str_replace(
                ['{{first_name}}', '{{company}}'],
                [$user->name, $companyName],
                $this->campaign->content
            );

            try {
                Mail::to($user->email)->send(
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
