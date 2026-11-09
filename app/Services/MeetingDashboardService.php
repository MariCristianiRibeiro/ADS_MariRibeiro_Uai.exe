<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Collection;

class MeetingDashboardService
{
    public function __construct(private readonly MeetingCostCalculator $costs) {}

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function data(User $user, array $filters): array
    {
        $meetings = $this->visibleMeetings($user);
        $meetings = $meetings->filter(fn (Meeting $meeting): bool => $this->matches($meeting, $filters))->values();
        $canViewCosts = $user->canViewCosts();
        $actionItems = $meetings->flatMap->actionItems->reject(fn (MeetingActionItem $item): bool => $item->status === 'canceled');
        $completed = $actionItems->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed' || $item->completed_at !== null);
        $completedWithDeadline = $completed->filter(fn (MeetingActionItem $item): bool => $item->due_date !== null && $item->completed_at !== null);
        $onTime = $completedWithDeadline->filter(fn (MeetingActionItem $item): bool => $item->completed_at->toDateString() <= $item->due_date->toDateString());
        $actualMeetings = $meetings->filter(fn (Meeting $meeting): bool => $meeting->actual_duration_seconds !== null);
        $actualSeconds = $actualMeetings->sum('actual_duration_seconds');
        $collaboratorSeconds = $actualMeetings->sum(fn (Meeting $meeting): int => $meeting->meetingParticipants->filter(fn ($participant): bool => in_array($participant->attendance_status, ['present', 'confirmed'], true))->count() * (int) $meeting->actual_duration_seconds);
        $decisions = $meetings->sum(fn (Meeting $meeting): int => max($meeting->decisions->count(), (int) ($meeting->conclusion?->decisions_count ?? 0)));
        $actualCost = $canViewCosts && $actualMeetings->isNotEmpty() ? $actualMeetings->sum(fn (Meeting $meeting): float => $this->costs->total($meeting)) : null;
        $estimatedCost = $canViewCosts ? $meetings->sum(fn (Meeting $meeting): float => $this->costs->estimatedTotal($meeting)) : null;
        $recurringMonthlyProjection = $canViewCosts ? $meetings->filter(fn (Meeting $meeting): bool => $meeting->recurrence_type === 'recurring')
            ->sum(fn (Meeting $meeting): float => $this->costs->estimatedTotal($meeting) * $this->monthlyOccurrences($meeting)) : null;
        $recurringAnnualProjection = $canViewCosts ? $meetings->filter(fn (Meeting $meeting): bool => $meeting->recurrence_type === 'recurring')
            ->sum(fn (Meeting $meeting): float => $this->costs->estimatedTotal($meeting) * $this->annualOccurrences($meeting)) : null;
        $financial = $canViewCosts ? $this->financialBreakdowns($meetings, $user->hasPermission('reports.individuals')) : [];
        $completedDurations = $completed->filter(fn (MeetingActionItem $item): bool => $item->completed_at !== null);
        $productivityScores = $meetings->map(fn (Meeting $meeting) => $meeting->conclusion?->productivity_score)->filter(fn ($score) => $score !== null);
        $additionalMeetings = $actionItems->pluck('follow_up_meeting_id')->filter()->unique()->count();
        $additionalRequests = $actionItems->filter(fn (MeetingActionItem $item): bool => $item->needs_additional_meeting)->count();
        $recurrenceCounts = $meetings->filter(fn (Meeting $meeting): bool => $meeting->recurrence_type === 'recurring')->groupBy(fn (Meeting $meeting) => $meeting->recurrence_frequency ?? 'recorrência não definida')->map->count();

        return [
            'meetings' => $meetings,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($this->visibleMeetings($user), $user->hasPermission('reports.individuals')),
            'canViewCosts' => $canViewCosts,
            'canViewIndividuals' => $user->hasPermission('reports.individuals'),
            'canExport' => $user->hasPermission('reports.export'),
            'metrics' => [
                'meeting_count' => $meetings->count(),
                'collaborator_hours' => round($collaboratorSeconds / 3600, 2),
                'meeting_hours' => round($actualSeconds / 3600, 2),
                'actual_meetings' => $actualMeetings->count(),
                'actual_cost' => $actualCost === null ? null : round($actualCost, 2),
                'estimated_cost' => $estimatedCost === null ? null : round($estimatedCost, 2),
                'recurring_monthly_projection' => $recurringMonthlyProjection === null ? null : round($recurringMonthlyProjection, 2),
                'recurring_annual_projection' => $recurringAnnualProjection === null ? null : round($recurringAnnualProjection, 2),
                'average_actual_cost' => $actualCost === null || $actualMeetings->isEmpty() ? null : round($actualCost / $actualMeetings->count(), 2),
                'decisions' => $decisions,
                'action_items' => $actionItems->count(),
                'completion_rate' => $actionItems->isEmpty() ? null : round($completed->count() / $actionItems->count() * 100, 1),
                'on_time_rate' => $completedWithDeadline->isEmpty() ? null : round($onTime->count() / $completedWithDeadline->count() * 100, 1),
                'additional_meetings' => $additionalMeetings,
                'additional_meeting_requests' => $additionalRequests,
                'avg_resolution_hours' => $completedDurations->isEmpty() ? null : round($completedDurations->avg(fn (MeetingActionItem $item): float => $item->created_at->diffInHours($item->completed_at)), 1),
                'productivity' => $productivityScores->isEmpty() ? null : round($productivityScores->avg(), 1),
                'committed_this_week_hours' => round($this->weeklyCommittedHours($user) ?? 0, 2),
                'recurrence_counts' => $recurrenceCounts,
            ],
            'financial' => $financial,
            'productivity' => $this->productivityBreakdown($meetings),
            'participation' => $this->participationBreakdown($meetings, $user->hasPermission('reports.individuals'), $canViewCosts),
            'efficiency' => $this->efficiencyBreakdown($meetings),
            'history' => $this->monthlyHistory($meetings, $canViewCosts),
            'aiContext' => $this->aiContext($filters, $meetings, $actionItems, $actualCost, $estimatedCost, $recurringMonthlyProjection, $recurringAnnualProjection, $financial, $decisions, $collaboratorSeconds),
        ];
    }

    /** @return Collection<int, Meeting> */
    private function visibleMeetings(User $user): Collection
    {
        $query = Meeting::query()->with([
            'project.team.directorate', 'organizer', 'users.jobPosition', 'meetingParticipants.user.team',
            'meetingParticipants.user.directorate', 'meetingParticipants.user.jobPosition', 'meetingParticipants.costSnapshot.team', 'meetingParticipants.costSnapshot.directorate',
            'conclusion', 'decisions', 'actionItems', 'analyses',
        ]);
        $subordinateIds = $user->subordinates()->pluck('users.id');

        if (in_array($user->role, ['admin', 'executive'], true)) {
            // These roles have organization wide reporting access.
        } elseif ($user->role === 'manager') {
            $query->where(fn ($scope) => $scope->where('organizer_id', $user->id)
                ->orWhereHas('project.team', fn ($team) => $team->where('manager_id', $user->id))
                ->orWhereHas('project', fn ($projects) => $projects->where('manager_id', $user->id))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->whereIn('user_id', $subordinateIds))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $user->id)));
        } elseif (in_array($user->role, ['director', 'director_commercial', 'director_network'], true)) {
            $query->where(fn ($scope) => $scope->where('organizer_id', $user->id)
                ->orWhereHas('project.team', fn ($team) => $team->where('directorate_id', $user->directorate_id))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->whereIn('user_id', $subordinateIds))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $user->id)));
        } else {
            $query->where(fn ($scope) => $scope->where('organizer_id', $user->id)
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $user->id)));
        }

        return $query->get();
    }

    /** @param array<string, mixed> $filters */
    private function matches(Meeting $meeting, array $filters): bool
    {
        if (! empty($filters['date_from']) && $meeting->date->toDateString() < $filters['date_from']) {
            return false;
        }
        if (! empty($filters['date_to']) && $meeting->date->toDateString() > $filters['date_to']) {
            return false;
        }
        if (! empty($filters['department']) && (string) ($meeting->project?->team_id ?? '') !== (string) $filters['department']
            && ! $meeting->meetingParticipants->contains(fn ($participant): bool => (string) ($participant->costSnapshot?->team_id ?? $participant->user?->team_id) === (string) $filters['department'])) {
            return false;
        }
        if (! empty($filters['directorate']) && (string) ($meeting->project?->team?->directorate_id ?? '') !== (string) $filters['directorate']
            && ! $meeting->meetingParticipants->contains(fn ($participant): bool => (string) ($participant->costSnapshot?->directorate_id ?? $participant->user?->directorate_id) === (string) $filters['directorate'])) {
            return false;
        }
        if (! empty($filters['collaborator']) && ! $meeting->meetingParticipants->contains(fn ($participant): bool => (string) $participant->user_id === (string) $filters['collaborator'])) {
            return false;
        }
        if (! empty($filters['organizer']) && (string) $meeting->organizer_id !== (string) $filters['organizer']) {
            return false;
        }
        if (! empty($filters['category']) && $meeting->category !== $filters['category']) {
            return false;
        }
        if (! empty($filters['recurrence'])) {
            $recurrenceMatches = in_array($filters['recurrence'], ['single', 'recurring'], true)
                ? $meeting->recurrence_type === $filters['recurrence']
                : $meeting->recurrence_frequency === $filters['recurrence'];
            if (! $recurrenceMatches) {
                return false;
            }
        }
        if (! empty($filters['status']) && $meeting->status !== $filters['status']) {
            return false;
        }

        return true;
    }

    private function filterOptions(Collection $meetings, bool $canViewIndividuals): array
    {
        return [
            'teams' => $meetings->flatMap(fn (Meeting $meeting) => collect([$meeting->project?->team])->merge($meeting->meetingParticipants->map(fn ($participant) => $participant->costSnapshot?->team ?? $participant->user?->team)))->filter()->unique('id')->sortBy('name')->values(),
            'directorates' => $meetings->flatMap(fn (Meeting $meeting) => collect([$meeting->project?->team?->directorate])->merge($meeting->meetingParticipants->map(fn ($participant) => $participant->costSnapshot?->directorate ?? $participant->user?->directorate)))->filter()->unique('id')->sortBy('name')->values(),
            'users' => $canViewIndividuals ? $meetings->flatMap(fn (Meeting $meeting) => $meeting->meetingParticipants->pluck('user')->push($meeting->organizer))->filter()->unique('id')->sortBy('name')->values() : collect(),
            'categories' => $meetings->pluck('category')->filter()->unique()->sort()->values(),
        ];
    }

    private function financialBreakdowns(Collection $meetings, bool $canViewIndividuals): array
    {
        $realMeetings = $meetings->filter(fn (Meeting $meeting): bool => $meeting->actual_duration_seconds !== null);
        $byMeetingDimension = function (callable $label) use ($realMeetings): array {
            return $realMeetings->groupBy($label)->map(fn (Collection $items, string $groupLabel): array => [
                'label' => $groupLabel, 'count' => $items->count(),
                'cost' => round($items->sum(fn (Meeting $meeting): float => $this->costs->total($meeting)), 2),
            ])->values()->all();
        };
        $departmentCosts = [];
        $directorateCosts = [];
        foreach ($realMeetings as $meeting) {
            if ($meeting->meetingParticipants->isEmpty()) {
                $department = $meeting->project?->team?->name ?? 'Sem departamento';
                $directorate = $meeting->project?->team?->directorate?->name ?? 'Sem diretoria';
                $cost = $this->costs->total($meeting);
                $this->appendCostGroup($departmentCosts, $department, $cost);
                $this->appendCostGroup($directorateCosts, $directorate, $cost);

                continue;
            }
            foreach ($meeting->meetingParticipants->filter(fn ($participant): bool => in_array($participant->attendance_status, ['confirmed', 'present'], true)) as $participant) {
                $rate = (float) ($participant->costSnapshot?->hourly_rate ?? ($participant->user ? $this->costs->userRate($participant->user) : 0));
                $cost = $meeting->actual_duration_seconds / 3600 * $rate;
                $this->appendCostGroup($departmentCosts, $participant->costSnapshot?->team?->name ?? $participant->user?->team?->name ?? 'Sem departamento', $cost);
                $this->appendCostGroup($directorateCosts, $participant->costSnapshot?->directorate?->name ?? $participant->user?->directorate?->name ?? 'Sem diretoria', $cost);
            }
        }
        $organizerLabel = $canViewIndividuals ? fn (Meeting $meeting) => $meeting->organizer?->name ?? 'Sem organizador' : fn (Meeting $meeting) => 'Organizadores';

        return [
            'department' => $this->sortCostGroups($departmentCosts),
            'directorate' => $this->sortCostGroups($directorateCosts),
            'category' => $byMeetingDimension(fn (Meeting $meeting) => $meeting->category ?? 'Sem categoria'),
            'organizer' => $byMeetingDimension($organizerLabel),
            'period' => $byMeetingDimension(fn (Meeting $meeting) => $meeting->date->format('Y-m')),
        ];
    }

    /** @param array<string, array{label: string, count: int, cost: float}> $groups */
    private function appendCostGroup(array &$groups, string $label, float $cost): void
    {
        $groups[$label] ??= ['label' => $label, 'count' => 0, 'cost' => 0.0];
        $groups[$label]['count']++;
        $groups[$label]['cost'] += $cost;
    }

    /** @param array<string, array{label: string, count: int, cost: float}> $groups
     * @return array<int, array{label: string, count: int, cost: float}>
     */
    private function sortCostGroups(array $groups): array
    {
        foreach ($groups as &$group) {
            $group['cost'] = round($group['cost'], 2);
        }
        unset($group);

        return collect($groups)->sortByDesc('cost')->values()->all();
    }

    private function productivityBreakdown(Collection $meetings): array
    {
        return $meetings->groupBy(fn (Meeting $meeting) => $meeting->date->format('Y-m'))->map(fn (Collection $items): array => [
            'period' => $items->first()->date->format('Y-m'),
            'collaborator_hours' => round($items->sum(fn (Meeting $meeting): float => ($meeting->actual_duration_seconds ?? 0) * $meeting->meetingParticipants->filter(fn ($participant): bool => in_array($participant->attendance_status, ['present', 'confirmed'], true))->count() / 3600), 2),
            'decisions' => $items->sum(fn (Meeting $meeting): int => max($meeting->decisions->count(), (int) ($meeting->conclusion?->decisions_count ?? 0))),
            'completed_actions' => $items->sum(fn (Meeting $meeting): int => $meeting->actionItems->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed' || $item->completed_at !== null)->count()),
        ])->values()->all();
    }

    private function participationBreakdown(Collection $meetings, bool $canViewIndividuals, bool $canViewCosts): array
    {
        $rows = $meetings->flatMap(fn (Meeting $meeting) => $meeting->meetingParticipants->map(fn ($participant): array => [
            'name' => $canViewIndividuals ? ($participant->user?->name ?? 'Colaborador') : 'Participante',
            'meetings' => 1,
            'present' => in_array($participant->attendance_status, ['present', 'confirmed'], true) ? 1 : 0,
            'effective' => $participant->participation_effective === true ? 1 : 0,
            'contribution' => $participant->relevant_contribution === true ? 1 : 0,
            'cost' => $canViewCosts && $meeting->actual_duration_seconds !== null && in_array($participant->attendance_status, ['present', 'confirmed'], true)
                ? round(($meeting->actual_duration_seconds / 3600) * (float) ($participant->costSnapshot?->hourly_rate ?? $participant->user?->jobPosition?->hourly_rate ?? $participant->user?->hourly_rate ?? 0), 2) : null,
        ]));

        return $rows->groupBy('name')->map(fn (Collection $items): array => [
            'name' => $items->first()['name'], 'meetings' => $items->sum('meetings'), 'present' => $items->sum('present'),
            'effective' => $items->sum('effective'), 'contribution' => $items->sum('contribution'),
            'cost' => $canViewCosts ? round($items->sum('cost'), 2) : null,
        ])->sortByDesc('meetings')->values()->all();
    }

    private function efficiencyBreakdown(Collection $meetings): array
    {
        return $meetings->map(fn (Meeting $meeting): array => [
            'title' => $meeting->title, 'date' => $meeting->date->format('d/m/Y'), 'planned_minutes' => $meeting->duration_minutes,
            'actual_minutes' => $meeting->actual_duration_seconds === null ? null : round($meeting->actual_duration_seconds / 60),
            'actions' => $meeting->actionItems->count(),
            'additional_requests' => $meeting->actionItems->where('needs_additional_meeting', true)->count(),
            'recurrence' => $meeting->recurrence_type,
        ])->values()->all();
    }

    private function monthlyHistory(Collection $meetings, bool $canViewCosts): array
    {
        return $meetings->groupBy(fn (Meeting $meeting) => $meeting->date->format('Y-m'))->sortKeys()->map(fn (Collection $items): array => [
            'period' => $items->first()->date->format('Y-m'), 'meetings' => $items->count(),
            'collaborator_hours' => round($items->sum(fn (Meeting $meeting): float => ($meeting->actual_duration_seconds ?? 0) * $meeting->meetingParticipants->filter(fn ($participant): bool => in_array($participant->attendance_status, ['present', 'confirmed'], true))->count() / 3600), 2),
            'cost' => $canViewCosts ? round($items->sum(fn (Meeting $meeting): float => $this->costs->total($meeting)), 2) : null,
        ])->values()->all();
    }

    private function aiContext(array $filters, Collection $meetings, Collection $actions, ?float $actualCost, ?float $estimatedCost, ?float $recurringMonthlyProjection, ?float $recurringAnnualProjection, array $financial, int $decisions, int $collaboratorSeconds): array
    {
        $pendingActions = $actions->filter(fn (MeetingActionItem $item): bool => $item->status !== 'completed' && $item->completed_at === null);
        $predictions = $meetings->flatMap(fn (Meeting $meeting) => $meeting->analyses
            ->filter(fn ($analysis): bool => $analysis->status === 'completed' && in_array($analysis->analysis_type, ['pre_meeting', 'post_meeting'], true))
            ->map(fn ($analysis): array => ['type' => $analysis->analysis_type, 'summary' => data_get($analysis->result, 'summary'), 'meeting_result' => $meeting->conclusion?->objective_achievement]))
            ->filter(fn (array $prediction): bool => filled($prediction['summary']))->values()->all();

        return [
            'periodo' => ['de' => $filters['date_from'] ?? null, 'ate' => $filters['date_to'] ?? null], 'filtros' => collect($filters)->except(['collaborator', 'organizer'])->all(),
            'reunioes' => $meetings->count(), 'reunioes_com_duracao_real' => $meetings->whereNotNull('actual_duration_seconds')->count(),
            'horas_reuniao' => round($meetings->sum(fn (Meeting $meeting): float => ($meeting->actual_duration_seconds ?? 0) / 3600), 2),
            'horas_colaborador' => round($collaboratorSeconds / 3600, 2), 'decisoes' => $decisions,
            'encaminhamentos' => $actions->count(), 'encaminhamentos_concluidos' => $actions->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed' || $item->completed_at !== null)->count(),
            'encaminhamentos_pendentes' => $actions->filter(fn (MeetingActionItem $item): bool => $item->status !== 'completed' && $item->completed_at === null)->count(),
            'custos_apurados' => $actualCost, 'investimento_previsto' => $estimatedCost,
            'projecao_mensal_recorrencias' => $recurringMonthlyProjection, 'projecao_anual_recorrencias' => $recurringAnnualProjection,
            'custos_por_departamento' => $financial['department'] ?? [],
            'custos_por_diretoria' => $financial['directorate'] ?? [],
            'custos_por_categoria' => $financial['category'] ?? [],
            'custos_por_organizador' => $financial['organizer'] ?? [],
            'categorias' => $meetings->countBy('category')->all(), 'recorrencias' => $meetings->countBy('recurrence_type')->all(),
            'reunioes_adicionais_realizadas' => $actions->pluck('follow_up_meeting_id')->filter()->unique()->count(),
            'reunioes_adicionais_solicitadas' => $actions->where('needs_additional_meeting', true)->count(),
            'riscos_demandas_pendentes' => [
                'atrasadas' => $pendingActions->filter(fn (MeetingActionItem $item): bool => $item->due_date !== null && $item->due_date->toDateString() < now()->toDateString())->count(),
                'alta_prioridade' => $pendingActions->filter(fn (MeetingActionItem $item): bool => in_array($item->priority, ['high', 'urgent'], true))->count(),
                'com_motivo_de_atraso' => $pendingActions->filter(fn (MeetingActionItem $item): bool => filled($item->delay_reason))->count(),
            ],
            'previsoes_preservadas_e_resultados' => $predictions,
            'pauta_concluida' => $meetings->map(fn (Meeting $meeting) => ['categoria' => $meeting->category, 'objetivo' => $meeting->objective, 'resultado' => $meeting->conclusion?->objective_achievement])->all(),
        ];
    }

    private function weeklyCommittedHours(User $user): ?float
    {
        $minutes = Meeting::query()->whereBetween('date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])
            ->whereNotIn('status', ['canceled'])->where(fn ($query) => $query->where('organizer_id', $user->id)->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $user->id)))
            ->sum('duration_minutes');

        return $minutes / 60;
    }

    private function monthlyOccurrences(Meeting $meeting): float
    {
        if ($meeting->recurrence_ends_at !== null && $meeting->recurrence_ends_at->toDateString() < now()->toDateString()) {
            return 0.0;
        }

        $interval = max(1, (int) ($meeting->recurrence_interval ?? 1));

        return match ($meeting->recurrence_frequency) {
            'daily' => 30 / $interval,
            'weekly' => 4.345 / $interval,
            'monthly' => 1 / $interval,
            'yearly' => 1 / (12 * $interval),
            default => 0.0,
        };
    }

    private function annualOccurrences(Meeting $meeting): int
    {
        $start = max(now()->startOfDay()->timestamp, $meeting->date->startOfDay()->timestamp);
        $end = now()->addYear()->startOfDay()->timestamp;
        if ($meeting->recurrence_ends_at !== null) {
            $end = min($end, $meeting->recurrence_ends_at->endOfDay()->timestamp);
        }
        if ($end < $start) {
            return 0;
        }

        $days = max(1, (int) floor(($end - $start) / 86400) + 1);
        $interval = max(1, (int) ($meeting->recurrence_interval ?? 1));

        return match ($meeting->recurrence_frequency) {
            'daily' => (int) ceil($days / $interval),
            'weekly' => (int) ceil($days / (7 * $interval)),
            'monthly' => (int) ceil($days / (30.4375 * $interval)),
            'yearly' => (int) ceil($days / (365.25 * $interval)),
            default => 0,
        };
    }
}
