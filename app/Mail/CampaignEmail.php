<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CampaignEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $subject;
    public $sender;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($content, $subject, $sender)
    {
        $this->content = $content;
        $this->subject = $subject;
        $this->sender = $sender;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from($this->sender)
                    ->subject($this->subject)
                    ->html($this->content);
    }
}
