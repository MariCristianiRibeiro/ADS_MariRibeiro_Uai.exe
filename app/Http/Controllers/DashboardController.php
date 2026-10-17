<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\User;
use App\Services\MeetingCostCalculator;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(MeetingCostCalculator $costCalculator): View
    {
        $meetings = Meeting::query()
            ->where('user_id', Auth::id())
            ->with(['users.jobPosition', 'users.participantProfile', 'participants.memberUser.jobPosition'])
            ->latest()
            ->get()
            ->map(function (Meeting $meeting) use ($costCalculator): array {
                $attendees = $meeting->users->isNotEmpty()
                    ? $meeting->users->map(fn (User $user): array => [
                        'name' => $user->name,
                        'hourlyRate' => $costCalculator->userRate($user),
                        'attendanceStatus' => $user->pivot->attendance_status,
                    ])->all()
                    : $meeting->participants->map(fn (Participant $participant): array => [
                        'name' => $participant->name,
                        'hourlyRate' => $costCalculator->participantRate($participant),
                        'attendanceStatus' => $participant->pivot->attendance_status,
                    ])->all();

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'date' => $meeting->date->format('d/m/Y'),
                    'status' => $meeting->status,
                    'actualDurationSeconds' => $meeting->actual_duration_seconds ?? 0,
                    'timerStartedAt' => $meeting->timer_started_at?->toIso8601String(),
                    'attendees' => $attendees,
                    'startUrl' => route('meetings.timer.start', $meeting),
                    'stopUrl' => route('meetings.timer.stop', $meeting),
                    'finishUrl' => route('meetings.finish', $meeting),
                ];
            })
            ->values();

        $activeMeeting = $meetings->first(fn (array $meeting): bool => $meeting['timerStartedAt'] !== null);
        $selectedMeetingId = $activeMeeting['id'] ?? $meetings->first()['id'] ?? null;

        return view('dashboard', [
            'usersCount' => User::count(),
            'participantsCount' => Participant::count(),
            'activeParticipantsCount' => Participant::where('status', 'active')->count(),
            'dashboardMeetings' => $meetings,
            'selectedMeetingId' => $selectedMeetingId,
        ]);
    }
}
