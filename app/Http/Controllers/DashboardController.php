<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\User;
use App\Services\MeetingCostCalculator;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(MeetingCostCalculator $costCalculator): View
    {
        $canViewCosts = Auth::user()->canViewCosts();
        $today = now('America/Sao_Paulo');
        $yearStart = $today->copy()->startOfYear();
        $quarterStart = $today->copy()->startOfQuarter();
        $monthStart = $today->copy()->startOfMonth();
        $actualMeetings = Meeting::query()
            ->whereNotNull('actual_duration_seconds')
            ->whereDate('date', '>=', $yearStart->toDateString())
            ->whereDate('date', '<=', $today->toDateString())
            ->with([
                'meetingParticipants.costSnapshot',
                'meetingParticipants.user.jobPosition',
                'meetingParticipants.user.participantProfile',
                'users.jobPosition',
                'users.participantProfile',
                'participants.memberUser.jobPosition',
            ])
            ->get();
        $periodMetrics = fn (Carbon $start, Carbon $end): array => $this->periodMetrics(
            $actualMeetings->filter(fn (Meeting $meeting): bool => $meeting->date->toDateString() >= $start->toDateString()
                && $meeting->date->toDateString() <= $end->toDateString()),
            $costCalculator,
            $canViewCosts,
        );
        $meetingInvestmentPeriods = [
            'year' => ['label' => 'Ano vigente', 'period' => $today->format('Y')] + $periodMetrics($yearStart, $today),
            'quarter' => ['label' => 'Trimestre vigente', 'period' => 'T'.$today->quarter.' - '.$today->format('Y')] + $periodMetrics($quarterStart, $today),
            'month' => ['label' => 'Mes vigente', 'period' => $today->format('m/Y')] + $periodMetrics($monthStart, $today),
        ];
        $todayDate = $today->toDateString();
        $meetings = Meeting::query()
            ->where('user_id', Auth::id())
            ->where(function ($query): void {
                $query->where('status', 'ongoing')
                    ->orWhere(fn ($scheduled) => $scheduled->upcomingScheduled());
            })
            ->with(['users.jobPosition', 'users.participantProfile', 'participants.memberUser.jobPosition', 'meetingParticipants.costSnapshot', 'meetingParticipants.user.jobPosition'])
            ->latest()
            ->get()
            ->map(function (Meeting $meeting) use ($costCalculator, $canViewCosts): array {
                $attendees = $meeting->users->isNotEmpty()
                    ? $meeting->users->map(function (User $user) use ($meeting, $costCalculator, $canViewCosts): array {
                        $attendee = [
                            'name' => $user->name,
                            'attendanceStatus' => $user->pivot->attendance_status,
                        ];
                        if ($canViewCosts) {
                            $attendee['hourlyRate'] = $costCalculator->meetingParticipantRate($meeting, $user);
                        }

                        return $attendee;
                    })->all()
                    : $meeting->participants->map(function (Participant $participant) use ($costCalculator, $canViewCosts): array {
                        $attendee = [
                            'name' => $participant->name,
                            'attendanceStatus' => $participant->pivot->attendance_status,
                        ];
                        if ($canViewCosts) {
                            $attendee['hourlyRate'] = $costCalculator->participantRate($participant);
                        }

                        return $attendee;
                    })->all();

                return [
                    'id' => $meeting->id,
                    'title' => $meeting->title,
                    'date' => $meeting->date->format('d/m/Y'),
                    'scheduledDate' => $meeting->date->toDateString(),
                    'status' => $meeting->status,
                    'actualDurationSeconds' => $meeting->actual_duration_seconds ?? 0,
                    'timerStartedAt' => $meeting->timer_started_at?->toIso8601String(),
                    'attendees' => $attendees,
                    'startUrl' => route('meetings.timer.start', $meeting),
                    'stopUrl' => route('meetings.timer.stop', $meeting),
                    'finishUrl' => route('meetings.finish', $meeting),
                    'closeoutUrl' => route('meetings.closeout', $meeting),
                    'editUrl' => route('meetings.edit', $meeting),
                ];
            })
            ->values();
        $pastMeetings = Meeting::query()
            ->where('user_id', Auth::id())
            ->whereIn('status', ['draft', 'scheduled'])
            ->whereNull('timer_started_at')
            ->whereDate('date', '<', $todayDate)
            ->orderBy('date')
            ->get(['id', 'title', 'date']);

        $activeMeeting = $meetings->first(fn (array $meeting): bool => $meeting['timerStartedAt'] !== null);
        $selectedMeetingId = $activeMeeting['id'] ?? $meetings->first()['id'] ?? null;

        return view('dashboard', [
            'usersCount' => User::count(),
            'participantsCount' => Participant::count(),
            'activeParticipantsCount' => Participant::where('status', 'active')->count(),
            'dashboardMeetings' => $meetings,
            'pastMeetings' => $pastMeetings,
            'selectedMeetingId' => $selectedMeetingId,
            'canViewCosts' => $canViewCosts,
            'meetingInvestmentPeriods' => $meetingInvestmentPeriods,
        ]);
    }

    /** @return array{cost: ?float, collaborator_hours: float} */
    private function periodMetrics(Collection $meetings, MeetingCostCalculator $costCalculator, bool $canViewCosts): array
    {
        return [
            'cost' => $canViewCosts ? round($meetings->sum(fn (Meeting $meeting): float => $costCalculator->total($meeting)), 2) : null,
            'collaborator_hours' => round($meetings->sum(fn (Meeting $meeting): float => $costCalculator->collaboratorHours($meeting)), 2),
        ];
    }
}
