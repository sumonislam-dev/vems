<?php

namespace App\Notifications;

use App\Models\Trip;
use Illuminate\Notifications\Notification;

class TripStatusUpdated extends Notification
{
    public function __construct(protected Trip $trip, protected string $status) {}

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
            'type' => 'trip_status_updated',
            'trip_id' => $this->trip->id,
            'trip_number' => $this->trip->trip_number,
            'status' => $this->status,
            'message' => $this->status === 'approved'
                ? "Your trip {$this->trip->trip_number} was approved."
                : "Your trip {$this->trip->trip_number} was rejected.",
            'url' => route('trips.show', $this->trip->id),
        ];
    }
}
