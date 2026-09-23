<?php

namespace App\Notifications;

use App\Models\TripFeedback;
use Illuminate\Notifications\Notification;

class ComplaintAssigned extends Notification
{
    public function __construct(protected TripFeedback $complaint) {}

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        return [
            'type' => 'complaint_assigned',
            'complaint_id' => $this->complaint->id,
            'subject' => $this->complaint->subject,
            'message' => "You were assigned: {$this->complaint->subject}",
            'url' => route('complaints.show', $this->complaint->id),
        ];
    }
}
