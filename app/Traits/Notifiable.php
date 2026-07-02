<?php

namespace App\Traits;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Notifiable
{
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function notify(User $user, string $type, string $title, ?string $body = null, array $data = []): Notification
    {
        return $this->notifications()->create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);
    }
}
