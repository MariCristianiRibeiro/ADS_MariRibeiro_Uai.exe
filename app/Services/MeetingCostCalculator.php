<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\User;

class MeetingCostCalculator
{
    public function participantRate(Participant $participant): float
    {
        return (float) ($participant->memberUser?->jobPosition?->hourly_rate
            ?? $participant->memberUser?->hourly_rate
            ?? $participant->hourly_rate
            ?? 0);
    }

    public function participantCost(Meeting $meeting, Participant $participant): float
    {
        return ($meeting->duration_minutes / 60) * $this->participantRate($participant);
    }

    public function userRate(User $user): float
    {
        return (float) ($user->jobPosition?->hourly_rate ?? $user->hourly_rate ?? 0);
    }

    public function userCost(Meeting $meeting, User $user): float
    {
        return ($meeting->duration_minutes / 60) * $this->userRate($user);
    }

    public function total(Meeting $meeting): float
    {
        $meeting->loadMissing('users.jobPosition', 'participants.memberUser.jobPosition');

        if ($meeting->users->isNotEmpty()) {
            return $meeting->users->sum(fn (User $user) => $this->userCost($meeting, $user));
        }

        return $meeting->participants->sum(fn (Participant $participant) => $this->participantCost($meeting, $participant));
    }

    public function byProject(iterable $meetings): array
    {
        $totals = [];

        foreach ($meetings as $meeting) {
            $projectKey = $meeting->project_id ?? 'without-project';
            $totals[$projectKey] = ($totals[$projectKey] ?? 0) + $this->total($meeting);
        }

        return $totals;
    }
}
