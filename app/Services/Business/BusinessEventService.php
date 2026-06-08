<?php

namespace App\Services\Business;

use Illuminate\Database\Eloquent\Model;

class BusinessEventService
{
    public function record(string $logName, string $description, array $properties = [], ?Model $subject = null): void
    {
        $activity = activity($logName)->withProperties($properties);

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activity->log($description);
    }
}
