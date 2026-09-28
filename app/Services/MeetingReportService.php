<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Support\Collection;

class MeetingReportService
{
    public function __construct(private readonly MeetingCostCalculator $costs)
    {
    }

    public function for(User $user): array
    {
        if (! $user->canViewCosts()) {
            return $this->collaboratorReport($user);
        }

        return [
            'visibility' => 'real_cost',
            'rows' => $this->aggregateByProject($this->visibleMeetings($user)),
        ];
    }

    private function collaboratorReport(User $user): array
    {
        $meetings = Meeting::query()
            ->where(function ($query) use ($user) {
                $query->whereHas('users', fn ($users) => $users->where('users.id', $user->id))
                    ->orWhereHas('participants', fn ($participants) => $participants->where('participants.member_user_id', $user->id));
            })
            ->where('status', '!=', 'canceled')
            ->whereDate('date', '>=', now()->toDateString())
            ->with('project')
            ->get();

        $hours = $meetings->sum(fn (Meeting $meeting) => $meeting->duration_minutes / 60);

        return [
            'visibility' => $meetings->count() >= 4 ? 'estimated_hours' : 'hours',
            'future_meetings' => $meetings->count(),
            'hours' => round($hours, 2),
            'rows' => $meetings->groupBy('project_id')->map(fn (Collection $projectMeetings) => [
                'project' => $projectMeetings->first()->project?->name ?? 'Sem projeto',
                'hours' => round($projectMeetings->sum(fn (Meeting $meeting) => $meeting->duration_minutes / 60), 2),
            ])->values()->all(),
        ];
    }

    private function visibleMeetings(User $user): Collection
    {
        $query = Meeting::query()
            ->where('status', '!=', 'canceled')
            ->with(['project.team.directorate', 'users.jobPosition', 'participants.memberUser.jobPosition']);

        $subordinateIds = $user->subordinates()->pluck('users.id');

        if ($user->role === 'manager') {
            $query->where(function ($scope) use ($user, $subordinateIds) {
                $scope->whereHas('project.team', fn ($team) => $team->where('manager_id', $user->id))
                    ->orWhereHas('users', fn ($users) => $users->whereIn('users.id', $subordinateIds))
                    ->orWhereHas('participants', fn ($participants) => $participants->whereIn('participants.member_user_id', $subordinateIds));
            });
        } elseif (in_array($user->role, ['director', 'director_commercial', 'director_network'], true)) {
            $query->where(function ($scope) use ($user, $subordinateIds) {
                $scope->whereHas('project.team', fn ($team) => $team->where('directorate_id', $user->directorate_id))
                    ->orWhereHas('users', fn ($users) => $users->whereIn('users.id', $subordinateIds))
                    ->orWhereHas('participants', fn ($participants) => $participants->whereIn('participants.member_user_id', $subordinateIds));
            });
        } elseif (! in_array($user->role, ['admin', 'executive'], true)) {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    private function aggregateByProject(Collection $meetings): array
    {
        return $meetings->groupBy('project_id')->map(function (Collection $projectMeetings) {
            $first = $projectMeetings->first();

            return [
                'project' => $first->project?->name ?? 'Sem projeto',
                'team' => $first->project?->team?->name,
                'directorate' => $first->project?->team?->directorate?->name,
                'meetings' => $projectMeetings->count(),
                'hours' => round($projectMeetings->sum(fn (Meeting $meeting) => $meeting->duration_minutes / 60), 2),
                'real_cost' => round($projectMeetings->sum(fn (Meeting $meeting) => $this->costs->total($meeting)), 2),
            ];
        })->values()->all();
    }
}
