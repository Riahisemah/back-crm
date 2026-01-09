<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduledEmail extends Model
{
    use HasFactory;

    protected $fillable = [
        'campaign_id',
        'user_id',
        'organisation_id',
        'to_email',
        'subject',
        'body',
        'status',
        'scheduled_for',
        'sent_at',
        'attempts',
        'error_message',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_for' => 'datetime',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the campaign that owns this scheduled email.
     */
public function campaign()
{
    return $this->belongsTo(EmailCampaign::class, 'campaign_id');
}

    /**
     * Get the user that scheduled this email.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the organisation.
     */
    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Scope for emails that are ready to send.
     */
    public function scopeReadyToSend($query)
    {
        return $query->whereIn('status', ['pending', 'scheduled'])
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for', 'asc');
    }

    /**
     * Mark email as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark email as sent.
     */
    public function markAsSent(string $messageId = null): void
    {
        $metadata = $this->metadata ?? [];
        if ($messageId) {
            $metadata['message_id'] = $messageId;
        }

        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Mark email as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    /**
     * Cancel this scheduled email.
     */
    public function cancel(): bool
    {
        if (in_array($this->status, ['pending', 'scheduled'])) {
            return $this->update(['status' => 'cancelled']);
        }
        return false;
    }

    /**
     * Check if email can be sent now.
     */
    public function canBeSentNow(): bool
    {
        return in_array($this->status, ['pending', 'scheduled']) && 
               (!$this->scheduled_for || $this->scheduled_for->lte(now()));
    }
}