<?php

namespace App\Observers;

use App\Models\Activity;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityObserver
{
    public function created(Activity $activity): void
    {
        // Skip replies — handled by controller-side reply notification
        if ($activity->reply_to_id) {
            return;
        }

        if ($activity->lead_id) {
            $this->notifyLeadOwner($activity);
        }

        if ($activity->opportunity_id) {
            $this->notifyOpportunityOwner($activity);
        }
    }

    private function notifyLeadOwner(Activity $activity): void
    {
        $lead = Lead::with('leadOwner')->find($activity->lead_id);
        if (! $lead || ! $lead->leadOwner) {
            return;
        }

        $owner = $lead->leadOwner;
        if ($owner->id === $activity->user_id) {
            return;
        }

        $preview = $activity->content
            ? Str::limit($activity->content, 80)
            : 'Mengirim lampiran';

        Notification::create([
            'user_id' => $owner->id,
            'type' => 'mention',
            'title' => "Aktivitas baru: {$lead->lead_title}",
            'body' => $preview,
            'notifiable_type' => Lead::class,
            'notifiable_id' => $lead->id,
            'data' => ['activity_id' => $activity->id, 'lead_id' => $lead->id],
        ]);
    }

    private function notifyOpportunityOwner(Activity $activity): void
    {
        $opportunity = Opportunity::with('owner')->find($activity->opportunity_id);
        if (! $opportunity || ! $opportunity->owner) {
            return;
        }

        $owner = $opportunity->owner;
        if ($owner->id === $activity->user_id) {
            return;
        }

        $preview = $activity->content
            ? Str::limit($activity->content, 80)
            : 'Mengirim lampiran';

        Notification::create([
            'user_id' => $owner->id,
            'type' => 'mention',
            'title' => "Aktivitas baru: {$opportunity->opportunity_name}",
            'body' => $preview,
            'notifiable_type' => Opportunity::class,
            'notifiable_id' => $opportunity->id,
            'data' => ['activity_id' => $activity->id, 'opportunity_id' => $opportunity->id],
        ]);
    }
}
