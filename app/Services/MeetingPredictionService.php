<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingPrediction;
use App\Models\MeetingPredictionEvaluation;
use App\Models\MeetingRecommendationFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MeetingPredictionService
{
    private const MODEL_VERSION = '1.0.0';

    private const MIN_TRAINING_OBSERVATIONS = 10;

    private const MIN_VALIDATION_OBSERVATIONS = 5;

    private const FORECAST_TYPES = [
        'actual_duration_minutes' => 'minutos',
        'meetings_to_conclude' => 'reuniões',
        'on_time_probability' => 'probabilidade',
        'additional_meeting_risk' => 'probabilidade',
        'resolution_days' => 'dias',
    ];

    public function __construct(private readonly MeetingCostCalculator $costs) {}

    /** @return Collection<int, MeetingPrediction> */
    public function generateFor(Meeting $meeting, ?User $requester = null, bool $regenerate = false): Collection
    {
        $existing = $meeting->predictions()->get();
        if ($existing->isNotEmpty() && ! $regenerate) {
            return $existing;
        }

        $meeting->loadMissing([
            'project.team.directorate', 'meetingParticipants.user.team', 'meetingParticipants.user.directorate',
            'meetingParticipants.costSnapshot.team', 'meetingParticipants.costSnapshot.directorate',
            'meetingParticipants.costSnapshot.jobPosition', 'actionItems.dependencies', 'conclusion', 'users.team', 'decisions',
        ]);
        $features = $this->featuresFor($meeting);
        $history = $this->historicalCohort($meeting, $features, $requester);
        $generation = (int) $meeting->predictions()->max('generation') + 1;
        $created = collect();

        foreach (self::FORECAST_TYPES as $type => $unit) {
            $observations = $history->map(fn (array $row): ?float => $row['outcomes'][$type] ?? null)->filter(fn ($value): bool => $value !== null)->values();
            $validation = $this->backtest($history, $type);
            $validated = $observations->count() >= self::MIN_TRAINING_OBSERVATIONS
                && $validation['observations'] >= self::MIN_VALIDATION_OBSERVATIONS;
            $predictedValue = $validated ? $this->statistic($observations, $type) : null;
            $modelName = in_array($type, ['on_time_probability', 'additional_meeting_risk'], true)
                ? 'historical_cohort_rate'
                : 'historical_cohort_median';

            $created->push($meeting->predictions()->create([
                'requested_by' => $requester?->id,
                'generation' => $generation,
                'prediction_type' => $type,
                'model_name' => $modelName,
                'model_version' => self::MODEL_VERSION,
                'status' => $validated ? 'validated' : 'insufficient_history',
                'predicted_at' => now(),
                'features' => $features + ['cohort' => 'categoria e similaridade de objetivo'],
                'predicted_value' => $predictedValue,
                'unit' => $unit,
                'confidence' => $validated ? $this->confidence($validation, $observations, $type) : null,
                'observations_count' => $observations->count(),
                'validation_observations' => $validation['observations'],
                'validation_metrics' => $validation + [
                    'backtest' => 'rolling_origin_last_five',
                    'minimum_training_observations' => self::MIN_TRAINING_OBSERVATIONS,
                    'confidence_meaning' => 'score derivado do erro fora da amostra; não é probabilidade de acerto',
                ],
            ]));
        }

        $deterministicValues = [
            'planned_cost' => [$this->costs->estimatedTotal($meeting), 'BRL', 'custo_planejado_deterministico'],
        ];
        if ($meeting->recurrence_type === 'recurring') {
            $deterministicValues['recurring_monthly_cost'] = [$this->costs->estimatedTotal($meeting) * $this->monthlyOccurrences($meeting), 'BRL/mês', 'recorrencia_deterministica'];
            $deterministicValues['recurring_annual_cost'] = [$this->costs->estimatedTotal($meeting) * $this->annualOccurrences($meeting), 'BRL/ano', 'recorrencia_deterministica'];
        }
        foreach ($deterministicValues as $type => [$value, $deterministicUnit, $modelName]) {
            $created->push($meeting->predictions()->create([
                'requested_by' => $requester?->id,
                'generation' => $generation,
                'prediction_type' => $type,
                'model_name' => $modelName,
                'model_version' => 'deterministic-1.0.0',
                'status' => 'deterministic',
                'predicted_at' => now(),
                'features' => $features + ['formula' => 'MeetingCostCalculator::estimatedTotal × duração/frequência cadastrada'],
                'predicted_value' => round((float) $value, 4),
                'unit' => $deterministicUnit,
                'confidence' => null,
                'observations_count' => 0,
                'validation_observations' => 0,
                'validation_metrics' => null,
            ]));
        }

        return $created;
    }

    /** @return array<int, array<string, mixed>> */
    public function recommendationsFor(Meeting $meeting, ?User $viewer = null): array
    {
        $meeting->loadMissing(['meetingParticipants.costSnapshot', 'meetingParticipants.user.team', 'actionItems.dependencies', 'conclusion', 'decisions']);
        $features = $this->featuresFor($meeting);
        $history = $this->historicalCohort($meeting, $features, $viewer);
        $completedHistory = $history->filter(fn (array $row): bool => $row['meeting']->status === 'completed');
        $items = [];
        $count = $features['participant_count'];
        $agendaWords = $features['agenda_word_count'];

        if (Meeting::query()->where('user_id', $meeting->user_id)->count() <= 1) {
            $items[] = $this->recommendation('first_meeting', 'Prepare a primeira reunião com um resultado verificável', 'Ainda não há histórico suficiente do organizador para comparar resultados.', 'Defina o objetivo, limite a pauta aos itens que exigem decisão e registre responsáveis e prazos no encerramento.');
        }
        if (($meeting->duration_minutes ?? 0) > 60) {
            $items[] = $this->recommendation('long_duration', 'Quebre a pauta em blocos com tempo definido', 'A duração prevista ultrapassa uma hora, aumentando o investimento de todos os participantes.', 'Separe os assuntos por prioridade, reserve tempo para decisões e mova atualizações informativas para um resumo assíncrono.', [
                'estimated_saving' => round($this->costs->estimatedTotal($meeting) * min(15, (int) $meeting->duration_minutes) / max(1, (int) $meeting->duration_minutes), 2),
                'saving_scenario_minutes' => min(15, (int) $meeting->duration_minutes),
            ]);
        }
        if ($count > 8) {
            $items[] = $this->recommendation('many_participants', 'Revise quem precisa participar de toda a reunião', "O convite inclui {$count} participantes.", 'Convide para o encontro quem precisa decidir ou executar; compartilhe o resumo com quem só precisa acompanhar. Não avalie desempenho individual com essa redução.');
        }
        if ($agendaWords > 250 || $features['agenda_item_count'] > 8) {
            $items[] = $this->recommendation('extensive_agenda', 'Priorize e divida a pauta extensa', 'O volume de pauta pode exceder o tempo disponível.', 'Agrupe itens relacionados, identifique a decisão esperada em cada bloco e transfira informes sem debate para um documento.');
        }
        if ($meeting->recurrence_type === 'recurring' && in_array($meeting->recurrence_frequency, ['daily', 'weekly'], true)) {
            $items[] = $this->recommendation('excessive_recurrence', 'Confirme se a recorrência ainda precisa desta frequência', 'O agendamento é diário ou semanal.', 'Teste uma cadência menor ou reuniões acionadas por necessidade e compare entregas antes de manter a frequência atual.');
        }

        if ($completedHistory->count() >= self::MIN_TRAINING_OBSERVATIONS) {
            $actions = $completedHistory->flatMap(fn (array $row) => $row['meeting']->actionItems->reject(fn (MeetingActionItem $item): bool => $item->status === 'canceled'));
            $withDeadline = $actions->filter(fn (MeetingActionItem $item): bool => $item->due_date !== null);
            $completedWithDeadline = $withDeadline->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed' && $item->completed_at !== null);
            $completionRate = $actions->isEmpty() ? null : $actions->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed')->count() / $actions->count();
            $followUpRate = $completedHistory->filter(fn (array $row): bool => $row['meeting']->actionItems->contains(fn (MeetingActionItem $item): bool => $item->needs_additional_meeting || $item->follow_up_meeting_id !== null))->count() / $completedHistory->count();
            if ($completionRate !== null && $completionRate < 0.7) {
                $items[] = $this->recommendation('low_completion', 'Feche cada assunto com responsável e próximo passo', 'O histórico comparável apresenta baixa conclusão de encaminhamentos.', 'Reduza a quantidade de decisões por encontro, atribua um responsável a cada demanda e revise pendências no início da próxima agenda.', ['historical_completion_rate' => round($completionRate * 100, 1), 'observations' => $completedHistory->count()]);
            }
            if ($followUpRate >= 0.25) {
                $items[] = $this->recommendation('frequent_follow_up', 'Valide a pauta e as dependências antes de confirmar', 'Reuniões semelhantes frequentemente registram necessidade de acompanhamento adicional.', 'Envie materiais antes do encontro, confirme pré-requisitos e concentre as decisões que dependem umas das outras.', ['historical_follow_up_rate' => round($followUpRate * 100, 1), 'observations' => $completedHistory->count()]);
            }
            $unassigned = $actions->filter(fn (MeetingActionItem $item): bool => $item->status !== 'canceled' && $item->responsible_user_id === null)->count();
            if ($unassigned > 0) {
                $items[] = $this->recommendation('missing_responsibles', 'Atribua responsáveis às demandas abertas', "O histórico comparável tem {$unassigned} encaminhamento(s) sem responsável.", 'Registre uma pessoa responsável e um prazo para cada entrega antes de encerrar a pauta.');
            }
        }

        return $items;
    }

    public function recordFeedback(Meeting $meeting, User $user, string $key, string $decision, ?string $rationale = null): MeetingRecommendationFeedback
    {
        $recommendation = collect($this->recommendationsFor($meeting))->firstWhere('key', $key);
        abort_if($recommendation === null, 422, 'A recomendação não está mais disponível para esta reunião. Atualize a página e tente novamente.');

        return $meeting->recommendationFeedback()->create([
            'user_id' => $user->id,
            'recommendation_key' => $key,
            'recommendation_title' => $recommendation['title'],
            'decision' => $decision,
            'rationale' => $rationale,
            'estimated_saving' => $recommendation['estimated_saving'] ?? null,
        ]);
    }

    /** Evaluate stored predictions from real outcomes; it never retrains or changes model versions. */
    public function evaluatePending(?int $meetingId = null): int
    {
        $query = MeetingPrediction::query()->whereNotNull('predicted_value')->with(['meeting.meetingParticipants.costSnapshot', 'meeting.meetingParticipants.user', 'meeting.actionItems', 'evaluations']);
        if ($meetingId !== null) {
            $query->where('meeting_id', $meetingId);
        }
        $evaluated = 0;

        foreach ($query->get() as $prediction) {
            $actual = $this->actualValue($prediction->meeting, $prediction->prediction_type);
            if ($actual === null || $prediction->predicted_value === null) {
                continue;
            }

            $error = $actual - (float) $prediction->predicted_value;
            $latestEvaluation = $prediction->evaluations->last();
            if ($latestEvaluation !== null && abs((float) $latestEvaluation->actual_value - $actual) < 0.0001) {
                continue;
            }
            $evaluationVersion = (int) ($latestEvaluation?->evaluation_version ?? 0) + 1;
            $snapshot = $this->actualSnapshot($prediction->meeting, $prediction->prediction_type, $actual);
            MeetingPredictionEvaluation::create([
                'meeting_prediction_id' => $prediction->id,
                'evaluation_version' => $evaluationVersion,
                'actual_value' => round($actual, 4),
                'error' => round($error, 4),
                'absolute_error' => round(abs($error), 4),
                'squared_error' => round($error ** 2, 4),
                'actual_snapshot' => $snapshot,
                'observed_at' => now(),
            ]);
            $prediction->update([
                'actual_value' => round($actual, 4),
                'error' => round($error, 4),
                'absolute_error' => round(abs($error), 4),
                'squared_error' => round($error ** 2, 4),
                'evaluated_at' => now(),
            ]);
            $evaluated++;
        }

        $this->evaluateAcceptedSavings($meetingId);

        return $evaluated;
    }

    /** @param Collection<int, Meeting> $selectedMeetings
     * @param  Collection<int, Meeting>  $visibleMeetings
     * @return array<string, mixed>
     */
    public function dashboard(Collection $selectedMeetings, Collection $visibleMeetings): array
    {
        $ids = $selectedMeetings->modelKeys();
        $predictions = MeetingPrediction::query()->whereIn('meeting_id', $ids)->latest('predicted_at')->with('evaluations')->get();
        $latestByMeetingType = $predictions->groupBy(fn (MeetingPrediction $prediction): string => $prediction->meeting_id.'-'.$prediction->prediction_type)
            ->map(fn (Collection $versions): MeetingPrediction => $versions->first());
        $latestEvaluations = $predictions->flatMap(fn (MeetingPrediction $prediction) => $prediction->evaluations->last() ? [$prediction->evaluations->last()->setRelation('prediction', $prediction)] : collect());
        $quality = $latestEvaluations->groupBy(fn (MeetingPredictionEvaluation $evaluation): string => $evaluation->prediction->prediction_type)->map(fn (Collection $rows): array => [
            'evaluations' => $rows->count(),
            'mae' => round((float) $rows->avg('absolute_error'), 3),
            'brier' => in_array($rows->first()->prediction->prediction_type, ['on_time_probability', 'additional_meeting_risk'], true)
                ? round((float) $rows->avg('squared_error'), 4) : null,
            'model' => $rows->first()->prediction->model_name.' '.$rows->first()->prediction->model_version,
        ])->all();
        $feedbackQuery = MeetingRecommendationFeedback::query()->whereIn('meeting_id', $ids)->latest('created_at')->get();
        $latestFeedback = $feedbackQuery->groupBy(fn (MeetingRecommendationFeedback $feedback): string => $feedback->meeting_id.'-'.$feedback->recommendation_key)
            ->map(fn (Collection $events): MeetingRecommendationFeedback => $events->first());
        $accepted = $latestFeedback->where('decision', 'accepted');
        $rejected = $latestFeedback->where('decision', 'rejected');
        $savingsEvaluated = $accepted->whereNotNull('realized_saving');
        $futureMeetings = $visibleMeetings->filter(fn (Meeting $meeting): bool => $meeting->date->betweenIncluded(now()->startOfDay(), now()->addDays(30)->endOfDay())
            && ! in_array($meeting->status, ['canceled', 'completed'], true));

        return [
            'predictions' => $latestByMeetingType->filter(fn (MeetingPrediction $prediction): bool => $prediction->status !== 'deterministic')->values(),
            'quality' => $quality,
            'feedback' => [
                'accepted' => $accepted->count(), 'rejected' => $rejected->count(),
                'estimated_saving' => round((float) $accepted->sum('estimated_saving'), 2),
                'realized_saving' => round((float) $savingsEvaluated->sum('realized_saving'), 2),
                'savings_evaluated' => $savingsEvaluated->count(),
            ],
            'future_department_hours' => $this->futureDepartmentHours($futureMeetings),
            'agenda_conflicts' => $this->agendaConflicts($futureMeetings),
            'participation_bottlenecks' => $this->participationBottlenecks($selectedMeetings),
        ];
    }

    /** @return array<string, mixed> */
    private function featuresFor(Meeting $meeting): array
    {
        $participants = $meeting->meetingParticipants->isNotEmpty()
            ? $meeting->meetingParticipants->pluck('user_id')->filter()->unique()
            : $meeting->users->pluck('id')->unique();
        $departments = $meeting->meetingParticipants->map(fn ($participant): ?int => $participant->costSnapshot?->directorate_id ?? $participant->costSnapshot?->team_id ?? $participant->user?->directorate_id ?? $participant->user?->team_id)
            ->filter()->unique();
        $goalTokens = $this->tokens(implode(' ', array_filter([$meeting->title, $meeting->objective, $meeting->category])));
        $agendaItems = collect(preg_split('/\r\n|\r|\n/u', (string) $meeting->agenda) ?: [])->map(fn (string $item): string => trim($item))->filter();

        return [
            'category' => Str::lower(trim((string) $meeting->category)),
            'goal_tokens' => $goalTokens,
            'agenda_word_count' => count($this->tokens((string) $meeting->agenda)),
            'agenda_item_count' => $agendaItems->count(),
            'complexity_proxy' => min(1000, count($this->tokens((string) $meeting->agenda)) + $participants->count() * 2 + $departments->count() * 5),
            'participant_count' => $participants->count(),
            'department_count' => $departments->count(),
            'planned_duration_minutes' => (int) ($meeting->duration_minutes ?? 0),
            'actual_duration_minutes' => $meeting->actual_duration_seconds === null ? null : round($meeting->actual_duration_seconds / 60, 2),
            'decisions_count' => max($meeting->decisions?->count() ?? 0, (int) ($meeting->conclusion?->decisions_count ?? 0)),
            'action_items_count' => $meeting->actionItems->count(),
            'dependency_count' => $meeting->actionItems->sum(fn (MeetingActionItem $item): int => $item->dependencies()->count()),
            'completion_rate' => $meeting->actionItems->isEmpty() ? null : round($meeting->actionItems->where('status', 'completed')->count() / $meeting->actionItems->count(), 4),
            'recurrence_type' => $meeting->recurrence_type,
            'recurrence_frequency' => $meeting->recurrence_frequency,
        ];
    }

    /** @param array<string, mixed> $features
     * @return Collection<int, array{meeting: Meeting, features: array<string, mixed>, outcomes: array<string, ?float>}>
     */
    private function historicalCohort(Meeting $target, array $features, ?User $viewer = null): Collection
    {
        if ($features['category'] === '') {
            return collect();
        }

        $query = Meeting::query()->where('status', 'completed')->where('category', $target->category)
            ->whereDate('date', '<=', $target->date->toDateString())->where('created_at', '<', $target->created_at);
        $this->scopeHistoricalMeetings($query, $target, $viewer);

        return $query->with(['meetingParticipants.user', 'meetingParticipants.costSnapshot', 'actionItems.dependencies', 'conclusion', 'decisions'])
            ->orderByDesc('date')->orderByDesc('id')->limit(200)->get()->sortBy(['date', 'id'])->values()
            ->map(function (Meeting $historical): array {
                $historicalFeatures = $this->featuresFor($historical);

                return ['meeting' => $historical, 'features' => $historicalFeatures, 'outcomes' => $this->outcomesFor($historical)];
            })
            ->filter(fn (array $row): bool => $this->similarity($features, $row['features']) >= 0.35)
            ->values();
    }

    private function scopeHistoricalMeetings(Builder $query, Meeting $target, ?User $viewer): void
    {
        if ($viewer === null) {
            $query->where('user_id', $target->user_id);

            return;
        }
        if (in_array($viewer->role, ['admin', 'executive'], true)) {
            return;
        }
        $subordinateIds = $viewer->subordinates()->pluck('users.id');
        if ($viewer->role === 'manager') {
            $query->where(fn ($scope) => $scope->where('user_id', $viewer->id)
                ->orWhereHas('project.team', fn ($teams) => $teams->where('manager_id', $viewer->id))
                ->orWhereHas('project', fn ($projects) => $projects->where('manager_id', $viewer->id))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->whereIn('user_id', $subordinateIds))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $viewer->id)));

            return;
        }
        if (in_array($viewer->role, ['director', 'director_commercial', 'director_network'], true)) {
            $query->where(fn ($scope) => $scope->where('user_id', $viewer->id)
                ->orWhereHas('project.team', fn ($teams) => $teams->where('directorate_id', $viewer->directorate_id))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->whereIn('user_id', $subordinateIds))
                ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $viewer->id)));

            return;
        }
        $query->where(fn ($scope) => $scope->where('user_id', $viewer->id)
            ->orWhereHas('meetingParticipants', fn ($participants) => $participants->where('user_id', $viewer->id)));
    }

    /** @param array<string, mixed> $target
     * @param  array<string, mixed>  $candidate
     */
    private function similarity(array $target, array $candidate): float
    {
        if ($target['category'] === '' || $target['category'] !== $candidate['category']) {
            return 0.0;
        }

        $targetTokens = $target['goal_tokens'];
        $candidateTokens = $candidate['goal_tokens'];
        $union = array_unique(array_merge($targetTokens, $candidateTokens));
        $goalSimilarity = $union === [] ? 0.5 : count(array_intersect($targetTokens, $candidateTokens)) / count($union);
        $participantSimilarity = $this->relativeSimilarity((int) $target['participant_count'], (int) $candidate['participant_count']);
        $departmentSimilarity = $this->relativeSimilarity((int) $target['department_count'], (int) $candidate['department_count']);
        $agendaSimilarity = $this->relativeSimilarity((int) $target['agenda_item_count'], (int) $candidate['agenda_item_count']);

        return 0.5 + 0.25 * $goalSimilarity + 0.1 * $participantSimilarity + 0.1 * $departmentSimilarity + 0.05 * $agendaSimilarity;
    }

    private function relativeSimilarity(int $left, int $right): float
    {
        return max(0.0, 1 - abs($left - $right) / max(1, $left, $right));
    }

    /** @return array<string, ?float> */
    private function outcomesFor(Meeting $meeting): array
    {
        $actions = $meeting->actionItems->reject(fn (MeetingActionItem $item): bool => $item->status === 'canceled');
        $allActionsClosed = $actions->every(fn (MeetingActionItem $item): bool => $item->status === 'completed' && $item->completed_at !== null);
        $deadlineActions = $actions->filter(fn (MeetingActionItem $item): bool => $item->due_date !== null);
        $completedDeadlineActions = $deadlineActions->filter(fn (MeetingActionItem $item): bool => $item->status === 'completed' && $item->completed_at !== null);
        $hasUnlinkedFollowUp = $actions->contains(fn (MeetingActionItem $item): bool => $item->needs_additional_meeting && $item->follow_up_meeting_id === null);
        $followUpCount = $this->followUpMeetingCount($meeting);
        $meetingsToConclude = $allActionsClosed && ! $hasUnlinkedFollowUp ? 1 + $followUpCount : null;
        $onTimeRate = $allActionsClosed && $deadlineActions->isNotEmpty()
            ? $completedDeadlineActions->filter(fn (MeetingActionItem $item): bool => $item->completed_at->toDateString() <= $item->due_date->toDateString())->count() / $deadlineActions->count()
            : null;
        $resolutionDays = $allActionsClosed && $actions->isNotEmpty()
            ? $actions->max(fn (MeetingActionItem $item): int => $meeting->date->diffInDays($item->completed_at))
            : null;
        $hasAdditional = $actions->contains(fn (MeetingActionItem $item): bool => $item->needs_additional_meeting || $item->follow_up_meeting_id !== null);

        return [
            'actual_duration_minutes' => $meeting->actual_duration_seconds === null ? null : $meeting->actual_duration_seconds / 60,
            'meetings_to_conclude' => $meetingsToConclude,
            'on_time_probability' => $onTimeRate,
            'additional_meeting_risk' => $allActionsClosed ? (float) $hasAdditional : null,
            'resolution_days' => $resolutionDays === null ? null : (float) $resolutionDays,
        ];
    }

    private function followUpMeetingCount(Meeting $meeting): int
    {
        $visited = [$meeting->id => true];
        $pending = $meeting->actionItems->pluck('follow_up_meeting_id')->filter()->unique()->values();
        $count = 0;
        while ($pending->isNotEmpty()) {
            $ids = $pending->reject(fn (int $id): bool => isset($visited[$id]))->values();
            if ($ids->isEmpty()) {
                break;
            }
            $pending = collect();
            foreach (Meeting::query()->whereIn('id', $ids)->where('status', 'completed')->with('actionItems')->get() as $followUp) {
                $visited[$followUp->id] = true;
                $count++;
                $pending = $pending->merge($followUp->actionItems->pluck('follow_up_meeting_id')->filter());
            }
        }

        return $count;
    }

    /** @param Collection<int, array<string, mixed>> $history
     * @return array<string, float|int|null>
     */
    private function backtest(Collection $history, string $type): array
    {
        $errors = [];
        $squaredErrors = [];
        $validRows = $history->filter(fn (array $row): bool => $row['outcomes'][$type] !== null)->values();
        for ($index = self::MIN_TRAINING_OBSERVATIONS; $index < $validRows->count(); $index++) {
            $test = $validRows->get($index);
            $training = $validRows->take($index)->filter(fn (array $row): bool => $this->similarity($test['features'], $row['features']) >= 0.35)
                ->map(fn (array $row): float => (float) $row['outcomes'][$type])->values();
            if ($training->count() < self::MIN_TRAINING_OBSERVATIONS) {
                continue;
            }
            $error = (float) $test['outcomes'][$type] - $this->statistic($training, $type);
            $errors[] = abs($error);
            $squaredErrors[] = $error ** 2;
        }
        $errors = array_slice($errors, -self::MIN_VALIDATION_OBSERVATIONS);
        $squaredErrors = array_slice($squaredErrors, -self::MIN_VALIDATION_OBSERVATIONS);

        return [
            'observations' => count($errors),
            'mae' => $errors === [] ? null : round(array_sum($errors) / count($errors), 4),
            'brier' => $squaredErrors === [] ? null : round(array_sum($squaredErrors) / count($squaredErrors), 4),
        ];
    }

    /** @param Collection<int, float> $values */
    private function statistic(Collection $values, string $type): float
    {
        return in_array($type, ['on_time_probability', 'additional_meeting_risk'], true)
            ? (float) $values->avg()
            : (float) $values->median();
    }

    /** @param array<string, float|int|null> $validation
     * @param  Collection<int, float>  $observations
     */
    private function confidence(array $validation, Collection $observations, string $type): float
    {
        $quality = in_array($type, ['on_time_probability', 'additional_meeting_risk'], true)
            ? 1 - (float) ($validation['brier'] ?? 1)
            : 1 - (float) ($validation['mae'] ?? 0) / max(1, (float) $observations->avg());

        return round(max(0, min(1, $quality)), 4);
    }

    private function actualValue(Meeting $meeting, string $type): ?float
    {
        if ($meeting->status !== 'completed') {
            return null;
        }
        $outcome = $this->outcomesFor($meeting);

        return match ($type) {
            'planned_cost' => $meeting->actual_duration_seconds === null ? null : $this->costs->total($meeting),
            default => $outcome[$type] ?? null,
        };
    }

    /** @return array<string, mixed> */
    private function actualSnapshot(Meeting $meeting, string $type, float $actual): array
    {
        $outcomes = $this->outcomesFor($meeting);

        return [
            'prediction_type' => $type,
            'measured_value' => round($actual, 4),
            'meeting_status' => $meeting->status,
            'actual_duration_minutes' => $meeting->actual_duration_seconds === null ? null : round($meeting->actual_duration_seconds / 60, 2),
            'completed_actions' => $meeting->actionItems->where('status', 'completed')->count(),
            'total_actions' => $meeting->actionItems->reject(fn (MeetingActionItem $item): bool => $item->status === 'canceled')->count(),
            'meetings_to_conclude' => $outcomes['meetings_to_conclude'] ?? null,
            'on_time_probability' => $outcomes['on_time_probability'] ?? null,
            'additional_meeting_risk' => $outcomes['additional_meeting_risk'] ?? null,
            'resolution_days' => $outcomes['resolution_days'] ?? null,
        ];
    }

    private function evaluateAcceptedSavings(?int $meetingId): void
    {
        $query = MeetingRecommendationFeedback::query()->where('decision', 'accepted')->whereNull('evaluated_at')
            ->where('recommendation_key', 'long_duration')->whereNotNull('estimated_saving')->whereHas('meeting', fn ($meetings) => $meetings->where('status', 'completed')->whereNotNull('actual_duration_seconds'))
            ->with(['meeting.meetingParticipants.costSnapshot', 'meeting.meetingParticipants.user']);
        if ($meetingId !== null) {
            $query->where('meeting_id', $meetingId);
        }
        foreach ($query->get() as $feedback) {
            $meeting = $feedback->meeting;
            $minutesReduced = max(0, $meeting->duration_minutes - ($meeting->actual_duration_seconds / 60));
            if ($minutesReduced <= 0) {
                $realizedSavings = 0.0;
            } else {
                $realizedSavings = $meeting->meetingParticipants->filter(fn ($participant): bool => in_array($participant->attendance_status, ['present', 'confirmed'], true))
                    ->sum(fn ($participant): float => $minutesReduced / 60 * (float) ($participant->costSnapshot?->hourly_rate ?? ($participant->user ? $this->costs->userRate($participant->user) : 0)));
            }
            $feedback->update(['realized_saving' => round($realizedSavings, 2), 'evaluated_at' => now()]);
        }
    }

    /** @param Collection<int, Meeting> $meetings
     * @return array<int, array{department: string, hours: float, meetings: int}>
     */
    private function futureDepartmentHours(Collection $meetings): array
    {
        $hours = [];
        foreach ($meetings as $meeting) {
            $attendees = $meeting->meetingParticipants;
            if ($attendees->isEmpty()) {
                $attendees = $meeting->users->map(fn (User $user) => (object) ['user' => $user, 'costSnapshot' => null]);
            }
            foreach ($attendees as $attendee) {
                $department = $attendee->costSnapshot?->team?->name ?? $attendee->user?->team?->name ?? $meeting->project?->team?->name ?? 'Sem departamento';
                $hours[$department] ??= ['department' => $department, 'hours' => 0.0, 'meetings' => 0];
                $hours[$department]['hours'] += (int) $meeting->duration_minutes / 60;
                $hours[$department]['meetings']++;
            }
        }

        return collect($hours)->map(function (array $row): array {
            $row['hours'] = round($row['hours'], 2);

            return $row;
        })->sortByDesc('hours')->values()->all();
    }

    /** @param Collection<int, Meeting> $meetings */
    private function agendaConflicts(Collection $meetings): int
    {
        $slots = [];
        foreach ($meetings as $meeting) {
            if ($meeting->time === null) {
                continue;
            }
            $start = strtotime($meeting->date->toDateString().' '.$meeting->time);
            $end = $meeting->end_time !== null
                ? strtotime($meeting->date->toDateString().' '.$meeting->end_time)
                : $start + max(1, (int) $meeting->duration_minutes) * 60;
            if ($end <= $start) {
                $end += 86400;
            }
            $attendeeIds = $meeting->meetingParticipants->pluck('user_id')->all();
            if ($attendeeIds === []) {
                $attendeeIds = $meeting->users->modelKeys();
            }
            foreach ($attendeeIds as $userId) {
                $slots[$userId][] = [$meeting->id, $start, $end];
            }
        }
        $conflicts = [];
        foreach ($slots as $participantsSlots) {
            for ($left = 0; $left < count($participantsSlots); $left++) {
                for ($right = $left + 1; $right < count($participantsSlots); $right++) {
                    [$leftMeeting, $leftStart, $leftEnd] = $participantsSlots[$left];
                    [$rightMeeting, $rightStart, $rightEnd] = $participantsSlots[$right];
                    if ($leftMeeting !== $rightMeeting && $leftStart < $rightEnd && $rightStart < $leftEnd) {
                        $conflicts[collect([$leftMeeting, $rightMeeting])->sort()->implode('-')] = true;
                    }
                }
            }
        }

        return count($conflicts);
    }

    /** @param Collection<int, Meeting> $meetings
     * @return array<int, array{department: string, observations: int, absence_rate: float}>
     */
    private function participationBottlenecks(Collection $meetings): array
    {
        $rows = $meetings->filter(fn (Meeting $meeting): bool => $meeting->status === 'completed')
            ->flatMap(fn (Meeting $meeting) => $meeting->meetingParticipants->map(fn ($participant): array => [
                'department' => $participant->costSnapshot?->team?->name ?? $participant->user?->team?->name ?? 'Sem departamento',
                'absent' => $participant->attendance_status === 'absent' ? 1 : 0,
                'recorded' => in_array($participant->attendance_status, ['absent', 'present', 'confirmed'], true) ? 1 : 0,
            ]));

        return $rows->groupBy('department')->map(fn (Collection $items, string $department): array => [
            'department' => $department,
            'observations' => $items->sum('recorded'),
            'absence_rate' => $items->sum('recorded') === 0 ? 0 : round($items->sum('absent') / $items->sum('recorded') * 100, 1),
        ])->filter(fn (array $row): bool => $row['observations'] >= self::MIN_TRAINING_OBSERVATIONS)
            ->sortByDesc('absence_rate')->values()->all();
    }

    /** @return array<string, mixed> */
    private function recommendation(string $key, string $title, string $reason, string $steps, array $extra = []): array
    {
        return ['key' => $key, 'title' => $title, 'reason' => $reason, 'steps' => $steps] + $extra;
    }

    /** @return array<int, string> */
    private function tokens(string $text): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', Str::lower($text)) ?: [])->filter(fn (string $token): bool => mb_strlen($token) >= 4)
            ->unique()->values()->all();
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
