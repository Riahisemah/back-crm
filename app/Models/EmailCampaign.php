<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class EmailCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'audience',
        'content',
        'status',
        'job_id',
        'sent_count',
        'failed_count',
        'total_count', // FIXED: Changed from total_recipients to total_count
        'schedule',
        'schedule_time',
        'started_at',
        'completed_at',
        'cancelled_at',
        'last_processed_at',
        'error_message',
        'sender'
    ];

    protected $casts = [
        'audience' => 'array',
        'schedule_time' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'last_processed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the campaign.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender');
    }

    /**
     * Get the scheduled emails for this campaign.
     */
    public function scheduledEmails()
    {
        return $this->hasMany(ScheduledEmail::class, 'campaign_id');
    }

    /**
     * Scope for active campaigns.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['scheduled', 'sending', 'processing']);
    }

    /**
     * Check if campaign can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'scheduled', 'sending', 'processing']);
    }

    /**
     * Check if campaign can be updated.
     */
    public function canBeUpdated(): bool
    {
        return in_array($this->status, ['draft', 'scheduled']);
    }

    /**
     * Update campaign statistics.
     */
    public function updateStats(): void
    {
        $stats = $this->scheduledEmails()
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed
            ')
            ->first();
        
        $this->update([
            'sent_count' => $stats->sent ?? 0,
            'failed_count' => $stats->failed ?? 0,
            'total_count' => $stats->total ?? 0, // FIXED: Changed from total_recipients
            'last_processed_at' => now(),
        ]);

        // Update campaign status if all emails are processed
        $pending = $this->scheduledEmails()
            ->whereIn('status', ['pending', 'scheduled', 'processing'])
            ->count();
        
        if ($pending === 0 && in_array($this->status, ['sending', 'processing'])) {
            $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }
    
    /**
     * Get the campaign progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_count === 0) { // FIXED: Changed from total_recipients
            return 0;
        }
        
        $processed = $this->sent_count + $this->failed_count;
        return ($processed / $this->total_count) * 100; // FIXED: Changed from total_recipients
    }
    
    /**
     * Get total_recipients (alias for total_count for compatibility).
     */
    public function getTotalRecipientsAttribute(): int
    {
        return $this->total_count;
    }
    
    /**
     * Set total_recipients (alias for total_count for compatibility).
     */
    public function setTotalRecipientsAttribute($value): void
    {
        $this->attributes['total_count'] = $value;
    }
}