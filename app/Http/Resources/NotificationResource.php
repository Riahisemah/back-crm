<?php

// app/Http/Resources/NotificationResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        $ts = $this->timestamp instanceof \DateTime ? $this->timestamp : Carbon::parse($this->timestamp);

        return [
            'id' => (int)$this->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'time' => $ts->diffForHumans(),          // human readable for frontend
            'timestamp' => $ts->toIso8601String(),   // precise timestamp
            'read' => (bool)$this->read,
            'avatar' => $this->avatar,
            'relatedView' => $this->related_view,
            'actionData' => $this->action_data,
            'category' => $this->category,
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
