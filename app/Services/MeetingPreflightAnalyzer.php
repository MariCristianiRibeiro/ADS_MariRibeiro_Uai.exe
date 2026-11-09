<?php

namespace App\Services;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MeetingPreflightAnalyzer
{
    public function __construct(private MeetingCostCalculator $costCalculator, private MeetingAiService $aiService) {}

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function analyze(array $data, Collection $participants): array
    {
        $durationHours = (int) $data['duration_minutes'] / 60;
        $people = $participants->map(function (User $user) use ($durationHours, $data): array {
            $rate = $this->costCalculator->userRate($user);

            return [
                'id' => $user->id,
                'department' => $user->directorate?->name ?? $user->team?->name ?? 'Sem departamento',
                'position' => $user->jobPosition?->name ?? 'Cargo não informado',
                'meeting_role' => $data['participant_roles'][$user->id] ?? 'participant',
                'hourly_rate' => $rate,
                'cost' => $rate * $durationHours,
                'collaborator_hours' => $durationHours,
            ];
        });

        $total = (float) $people->sum('cost');
        $collaboratorHours = (float) $people->sum('collaborator_hours');
        $departments = $people->groupBy('department')->map(fn (Collection $rows): float => (float) $rows->sum('cost'))->all();
        $monthlyOccurrences = $this->monthlyOccurrences($data);
        $monthlyCost = $total * $monthlyOccurrences;
        $annualCost = $total * $this->annualOccurrences($data);
        $similar = $this->similarMeetings($data);
        $pendingItems = $this->pendingItems($data);
        $agenda = $this->weeklyAgendaImpact($data, $participants);
        $durationSavings = $total * 0.25;
        $participantSavings = $participants->count() > 1 ? (float) $people->sortBy('hourly_rate')->first()['cost'] : 0.0;
        $financialMetrics = Auth::user()?->canViewCosts() ? [
            'custo_estimado' => $total,
            'horas_colaborador' => $collaboratorHours,
            'duracao_reuniao_horas' => $durationHours,
            'custo_por_hora_de_reuniao' => $durationHours > 0 ? $total / $durationHours : 0,
            'projecao_mensal' => $monthlyCost,
            'projecao_anual' => $annualCost,
            'economia_reducao_duracao_25_porcento' => $durationSavings,
            'economia_retirada_participante' => $participantSavings,
        ] : ['observacao' => 'Dados financeiros omitidos por permissão.'];

        $aiInput = [
            'reuniao' => [
                'titulo' => $data['title'] ?? '',
                'objetivo' => $data['objective'] ?? '',
                'contexto' => $data['description'] ?? '',
                'pauta' => $data['agenda'] ?? '',
                'categoria' => $data['category'] ?? '',
                'resultado_esperado' => $data['expected_outcome'] ?? '',
                'observacoes' => $data['notes'] ?? '',
                'duracao_minutos' => (int) $data['duration_minutes'],
                'recorrencia' => [
                    'tipo' => $data['recurrence_type'] ?? 'single',
                    'frequencia' => $data['recurrence_frequency'] ?? null,
                    'intervalo' => $data['recurrence_interval'] ?? null,
                    'termina_em' => $data['recurrence_ends_at'] ?? null,
                ],
            ],
            'participantes' => $people->map(fn (array $person): array => [
                'cargo' => $person['position'], 'departamento' => $person['department'], 'papel' => $person['meeting_role'],
            ])->values(),
            'indicadores_calculados_pelo_sistema' => $financialMetrics,
            'historico_semelhante' => $similar->map(fn (Meeting $meeting): array => [
                'id' => $meeting->id,
                'titulo' => $meeting->title,
                'objetivo' => $meeting->objective,
                'resultado' => $meeting->conclusion?->outcome ?? $meeting->conclusion?->summary,
                'acoes_concluidas' => $meeting->actionItems->where('status', 'completed')->count(),
                'acoes_total' => $meeting->actionItems->count(),
                'exige_acompanhamento' => $meeting->actionItems->contains(fn ($item): bool => $item->requires_follow_up),
            ])->values(),
            'pendencias_relacionadas' => $pendingItems->map(fn ($item): array => [
                'titulo' => $item->title, 'status' => $item->status, 'reuniao_origem' => $item->meeting?->title,
            ])->values(),
            'impacto_agenda_semana' => [
                'reunioes_com_participantes' => $agenda->count(),
                'horas_agendadas' => (float) $agenda->sum('duration_minutes') / 60,
            ],
        ];

        try {
            $ai = $this->aiService->analyze('pre_meeting', $aiInput);
        } catch (\Throwable $exception) {
            $ai = null;
            $aiError = $exception->getMessage();
        }

        return [
            'participants' => $people,
            'total' => $total,
            'collaborator_hours' => $collaboratorHours,
            'meeting_hours' => $durationHours,
            'by_department' => $departments,
            'cost_per_meeting_hour' => $durationHours > 0 ? $total / $durationHours : 0,
            'monthly_occurrences' => $monthlyOccurrences,
            'monthly_cost' => $monthlyCost,
            'annual_cost' => $annualCost,
            'duration_savings' => $durationSavings,
            'duration_savings_hours' => $collaboratorHours * 0.25,
            'participant_savings' => $participantSavings,
            'weekly_agenda' => $agenda,
            'weekly_agenda_hours' => (float) $agenda->sum('duration_minutes') / 60,
            'similar_meetings' => $similar,
            'pending_items' => $pendingItems,
            'ai_result' => $ai['result'] ?? null,
            'ai_metadata' => $ai === null ? null : collect($ai)->except('result')->all(),
            'ai_input_data' => $aiInput,
            'ai_generated' => $ai !== null,
            'ai_error' => $ai === null ? ($aiError ?? 'O provedor de IA não está configurado.') : null,
        ];
    }

    /** @param array<string, mixed> $data */
    private function monthlyOccurrences(array $data): float
    {
        if (($data['recurrence_type'] ?? 'single') !== 'recurring') {
            return 0;
        }

        $interval = max(1, (int) ($data['recurrence_interval'] ?? 1));

        return match ($data['recurrence_frequency'] ?? null) {
            'daily' => 30 / $interval,
            'weekly' => 4.345 / $interval,
            'monthly' => 1 / $interval,
            'yearly' => 1 / (12 * $interval),
            default => 0.0,
        };
    }

    /** @param array<string, mixed> $data */
    private function annualOccurrences(array $data): float
    {
        if (($data['recurrence_type'] ?? 'single') !== 'recurring') {
            return 0;
        }

        $start = strtotime((string) $data['date']);
        $end = strtotime('+1 year', $start);
        if (filled($data['recurrence_ends_at'] ?? null)) {
            $end = min($end, strtotime((string) $data['recurrence_ends_at']));
        }
        $days = max(1, (int) floor(($end - $start) / 86400) + 1);
        $interval = max(1, (int) ($data['recurrence_interval'] ?? 1));

        return match ($data['recurrence_frequency'] ?? null) {
            'daily' => ceil($days / $interval),
            'weekly' => ceil($days / (7 * $interval)),
            'monthly' => ceil($days / (30.4375 * $interval)),
            'yearly' => ceil($days / (365.25 * $interval)),
            default => 0,
        };
    }

    /** @param array<string, mixed> $data */
    private function similarMeetings(array $data): Collection
    {
        $terms = collect([$data['title'] ?? '', $data['objective'] ?? '', $data['description'] ?? '', $data['agenda'] ?? '', $data['category'] ?? ''])
            ->flatMap(fn (?string $text): array => preg_split('/\s+/u', Str::lower($text ?? '')) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique()->take(5);

        if ($terms->isEmpty()) {
            return collect();
        }

        return Meeting::query()->where('user_id', Auth::id())->where('status', 'completed')
            ->where(function ($builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('objective', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('agenda', 'like', "%{$term}%")
                        ->orWhere('category', 'like', "%{$term}%");
                }
            })->with(['conclusion', 'actionItems'])->latest('date')->limit(5)->get();
    }

    /** @param array<string, mixed> $data */
    private function pendingItems(array $data): Collection
    {
        $terms = collect([$data['title'] ?? '', $data['objective'] ?? '', $data['description'] ?? '', $data['agenda'] ?? '', $data['category'] ?? ''])
            ->flatMap(fn (?string $text): array => preg_split('/\s+/u', Str::lower($text ?? '')) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique()->take(5);

        if ($terms->isEmpty()) {
            return collect();
        }

        return MeetingActionItem::query()->with('meeting:id,title,date')
            ->whereHas('meeting', fn ($query) => $query->where('user_id', Auth::id()))
            ->whereIn('status', ['open', 'in_progress', 'blocked'])
            ->where(function ($builder) use ($terms): void {
                foreach ($terms as $term) {
                    $builder->orWhere('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                }
            })->limit(10)->get();
    }

    /** @param array<string, mixed> $data */
    private function weeklyAgendaImpact(array $data, Collection $participants): Collection
    {
        $date = $data['date'];
        $start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
        $end = date('Y-m-d', strtotime('sunday this week', strtotime($date)));

        return Meeting::query()->where('user_id', Auth::id())->whereIn('status', ['scheduled', 'ongoing'])
            ->whereBetween('date', [$start, $end])
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $participants->modelKeys()))
            ->with('users:id,name')->get();
    }
}
