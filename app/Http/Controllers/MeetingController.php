<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAnalysis;
use App\Models\Participant;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingAiService;
use App\Services\MeetingCostCalculator;
use App\Services\MeetingPreflightAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('meetings.view'), 403);

        $query = Meeting::where('user_id', Auth::id())->latest();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('location', 'ilike', "%{$search}%")
                    ->orWhere('notes', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        return view('meetings.index', [
            'meetings' => $query->with(['project', 'users', 'participants'])->get(),
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
        ]);
    }

    public function startTimer(Meeting $meeting): JsonResponse
    {
        abort_unless(Auth::user()->hasPermission('meetings.edit'), 403);
        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting = DB::transaction(function () use ($meeting): Meeting {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            abort_unless($meeting->user_id === Auth::id(), 403);
            abort_if(
                in_array($meeting->status, ['completed', 'canceled'], true),
                422,
                'Não é possível iniciar o cronômetro de uma reunião encerrada ou cancelada.',
            );

            if ($meeting->timer_started_at === null) {
                $meeting->timer_started_at = now();
                $meeting->status = 'ongoing';
                $meeting->save();
            }

            return $meeting;
        });

        return response()->json([
            'actualDurationSeconds' => $meeting->actual_duration_seconds ?? 0,
            'timerStartedAt' => $meeting->timer_started_at?->toIso8601String(),
        ]);
    }

    public function stopTimer(Meeting $meeting): JsonResponse
    {
        abort_unless(Auth::user()->hasPermission('meetings.edit'), 403);
        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting = DB::transaction(function () use ($meeting): Meeting {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            abort_unless($meeting->user_id === Auth::id(), 403);
            abort_if($meeting->timer_started_at === null, 409, 'O cronômetro desta reunião não está em andamento.');

            $elapsedSeconds = max(0, now()->timestamp - $meeting->timer_started_at->timestamp);
            $meeting->actual_duration_seconds = ($meeting->actual_duration_seconds ?? 0) + $elapsedSeconds;
            $meeting->timer_started_at = null;
            $meeting->save();

            return $meeting;
        });

        return response()->json([
            'actualDurationSeconds' => $meeting->actual_duration_seconds,
            'timerStartedAt' => null,
        ]);
    }

    public function finish(Meeting $meeting): JsonResponse
    {
        abort_unless(Auth::user()->hasPermission('meetings.finish'), 403);
        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting = DB::transaction(function () use ($meeting): Meeting {
            $meeting = Meeting::query()->lockForUpdate()->findOrFail($meeting->id);

            abort_unless($meeting->user_id === Auth::id(), 403);

            if ($meeting->status === 'completed' && $meeting->timer_started_at === null) {
                return $meeting;
            }

            abort_if($meeting->status === 'canceled', 422, 'Não é possível finalizar uma reunião cancelada.');
            abort_unless(
                $meeting->status === 'ongoing' || $meeting->timer_started_at !== null,
                409,
                'Inicie a reunião antes de finalizá-la.',
            );

            if ($meeting->timer_started_at !== null) {
                $elapsedSeconds = max(0, now()->timestamp - $meeting->timer_started_at->timestamp);
                $meeting->actual_duration_seconds = ($meeting->actual_duration_seconds ?? 0) + $elapsedSeconds;
                $meeting->timer_started_at = null;
            }

            $meeting->status = 'completed';
            $meeting->save();

            return $meeting;
        });

        return response()->json([
            'actualDurationSeconds' => $meeting->actual_duration_seconds ?? 0,
            'timerStartedAt' => null,
            'status' => $meeting->status,
        ]);
    }

    public function create(MeetingCostCalculator $costCalculator): View
    {
        abort_unless(Auth::user()->hasPermission('meetings.create'), 403);

        $members = $this->availableMembers();

        return view('meetings.create', [
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => $members,
            'hourlyRates' => $members->mapWithKeys(fn (User $member): array => [$member->id => $costCalculator->userRate($member)])->all(),
        ]);
    }

    public function store(Request $request, MeetingCostCalculator $costCalculator): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.create'), 403);

        $token = $request->validate(['preflight_token' => ['required', 'uuid']])['preflight_token'];
        $preflight = Cache::pull($this->preflightCacheKey($request->user()->id, $token));
        if (! is_array($preflight) || ! isset($preflight['meeting_data'], $preflight['analysis'])) {
            return redirect()->route('meetings.create')->withErrors([
                'preflight_token' => 'A prévia expirou. Analise o investimento novamente antes de confirmar.',
            ]);
        }

        $request->replace($preflight['meeting_data']);

        $request->merge([
            'duration_minutes' => $request->input('duration_minutes', 60),
            'organizer_id' => $request->input('organizer_id', Auth::id()),
            'priority' => $request->input('priority', 'normal'),
            'recurrence_type' => $request->input('recurrence_type', 'single'),
        ]);

        $validated = $this->validateMeeting($request);
        $requestedIds = collect($validated['participants'] ?? []);
        $participantUserIds = $this->resolveParticipantUserIds($requestedIds);
        $previewParticipants = collect($preflight['analysis']['participants'])->keyBy('id');
        $currentParticipants = User::with(['jobPosition', 'team', 'directorate', 'participantProfile'])->whereKey($participantUserIds)->get();
        $participantContextChanged = $currentParticipants->contains(function (User $participant) use ($previewParticipants, $costCalculator): bool {
            $preview = $previewParticipants->get($participant->id);
            $department = $participant->directorate?->name ?? $participant->team?->name ?? 'Sem departamento';

            return ! is_array($preview)
                || round((float) $preview['hourly_rate'], 2) !== round($costCalculator->userRate($participant), 2)
                || $preview['position'] !== ($participant->jobPosition?->name ?? 'Cargo não informado')
                || $preview['department'] !== $department;
        });
        if ($participantContextChanged) {
            return redirect()->route('meetings.create')->withErrors([
                'preflight_token' => 'Os dados de cargo, departamento ou valor-hora mudaram desde a prévia. Revise e analise novamente.',
            ])->withInput($preflight['meeting_data']);
        }
        $attendance = $validated['attendance'] ?? [];
        $roles = $validated['participant_roles'] ?? [];
        $invitations = $validated['invitations'] ?? [];
        $confirmations = $validated['confirmations'] ?? [];
        unset($validated['attendance'], $validated['participants'], $validated['participant_roles'], $validated['invitations'], $validated['confirmations']);

        $meeting = DB::transaction(function () use ($validated, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations, $costCalculator, $preflight, $request): Meeting {
            $meeting = Meeting::create([...$validated, 'user_id' => Auth::id()]);
            $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations, $costCalculator);
            $this->storeAiAnalysis($meeting, $request->user()->id, 'pre_meeting', $preflight['analysis']);

            return $meeting;
        });

        return redirect()->route('meetings.index')->with('success', 'Reunião cadastrada com sucesso.');
    }

    public function preview(Request $request, MeetingPreflightAnalyzer $analyzer): View
    {
        abort_unless($request->user()->hasPermission('meetings.create'), 403);

        $request->merge([
            'duration_minutes' => $request->input('duration_minutes', 60),
            'organizer_id' => $request->input('organizer_id', Auth::id()),
            'priority' => $request->input('priority', 'normal'),
            'recurrence_type' => $request->input('recurrence_type', 'single'),
        ]);
        $validated = $this->validateMeeting($request);
        $userIds = $this->resolveParticipantUserIds(collect($validated['participants'] ?? []));
        $participants = User::with(['jobPosition', 'team', 'directorate', 'participantProfile'])
            ->whereKey($userIds)->get();

        $analysis = $analyzer->analyze($validated, $participants);
        $preflightToken = (string) Str::uuid();
        Cache::put($this->preflightCacheKey($request->user()->id, $preflightToken), [
            'meeting_data' => $validated,
            'analysis' => $analysis,
        ], now()->addMinutes(30));

        return view('meetings.preview', [
            'meetingData' => $validated,
            'analysis' => $analysis,
            'preflightToken' => $preflightToken,
            'canViewCosts' => $request->user()->canViewCosts(),
        ]);
    }

    public function adjust(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.create'), 403);

        return redirect()->route('meetings.create')->withInput($request->except('_token'));
    }

    public function show(Meeting $meeting, Request $request, MeetingAiService $aiService): View
    {
        abort_unless($request->user()->hasPermission('meetings.view'), 403);
        abort_unless($meeting->user_id === $request->user()->id, 403);

        $meeting->load([
            'project',
            'organizer',
            'meetingParticipants.user.jobPosition',
            'meetingParticipants.user.team',
            'meetingParticipants.user.directorate',
            'meetingParticipants.costSnapshot.jobPosition',
            'meetingParticipants.costSnapshot.team',
            'meetingParticipants.costSnapshot.directorate',
            'conclusion.recorder',
            'decisions.decisionMaker',
            'actionItems.responsible',
            'actionItems.directorate',
            'actionItems.dependencies',
            'history.actor',
            'analyses.requester',
        ]);

        return view('meetings.show', [
            'meeting' => $meeting,
            'members' => $meeting->users()->orderBy('name')->get(),
            'directorates' => Directorate::query()->where('active', true)->orderBy('name')->get(),
            'aiConfigured' => $aiService->isConfigured(),
            'aiProvider' => $aiService->provider(),
            'aiModel' => $aiService->configuredModel(),
        ]);
    }

    public function analyzeMeetingContext(Request $request, Meeting $meeting, MeetingAiService $aiService): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);
        $notesRules = $request->input('analysis_type') === 'pre_meeting'
            ? ['nullable', 'string', 'max:100000']
            : ['required', 'string', 'min:10', 'max:100000'];
        $validated = $request->validate([
            'analysis_type' => ['required', Rule::in(['pre_meeting', 'during_meeting', 'post_meeting'])],
            'analysis_notes' => $notesRules,
        ]);

        $stage = $validated['analysis_type'];
        if (($stage === 'pre_meeting' && ! in_array($meeting->status, ['draft', 'scheduled'], true))
            || ($stage === 'during_meeting' && $meeting->status !== 'ongoing')
            || ($stage === 'post_meeting' && $meeting->status !== 'completed')) {
            return back()->withErrors(['analysis_notes' => 'A etapa de análise não corresponde ao status atual da reunião.'])->withInput();
        }
        if ($stage !== 'pre_meeting' && blank($validated['analysis_notes'] ?? null)) {
            return back()->withErrors(['analysis_notes' => 'Registre as informações da reunião antes de pedir a análise.'])->withInput();
        }

        $meeting->loadMissing(['meetingParticipants.user.jobPosition', 'meetingParticipants.costSnapshot', 'decisions', 'actionItems.responsible', 'conclusion']);
        $inputData = $this->buildMeetingAnalysisContext($meeting, $stage, (string) ($validated['analysis_notes'] ?? ''));
        $version = (int) $meeting->analyses()->where('analysis_type', $stage)->max('version') + 1;
        $analysis = $meeting->analyses()->create([
            'requested_by' => $request->user()->id,
            'analysis_type' => $stage,
            'version' => $version,
            'prompt_version' => 'meeting-insights-v1',
            'transcript' => $validated['analysis_notes'] ?? '',
            'input_data' => $inputData,
            'provider' => config('services.ai.provider', 'gemini'),
            'model' => $aiService->configuredModel(),
            'status' => 'processing',
        ]);

        try {
            $this->updateAnalysisFromAi($analysis, $aiService->analyze($stage, $inputData));
        } catch (\Throwable $exception) {
            $analysis->update([
                'status' => 'failed',
                'error_message' => 'A chamada de IA falhou. Verifique conexão, provedor e chave de API.',
            ]);

            return back()->withErrors(['analysis_notes' => $exception->getMessage()])->withInput();
        }

        return back()->with('success', 'Análise gerada pela IA. Revise as sugestões; nada foi salvo como decisão ou encaminhamento.');
    }

    public function analyzeTranscript(Request $request, Meeting $meeting, MeetingAiService $aiService): RedirectResponse
    {
        return $this->analyzeMeetingContext($request, $meeting, $aiService);
    }

    public function edit(Meeting $meeting, MeetingCostCalculator $costCalculator): View
    {
        abort_unless(Auth::user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting->load(['users.jobPosition', 'users.participantProfile', 'meetingParticipants.costSnapshot']);
        $members = $this->availableMembers();

        return view('meetings.edit', [
            'meeting' => $meeting,
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => $members,
            'hourlyRates' => $members->mapWithKeys(fn (User $member): array => [
                $member->id => $costCalculator->meetingParticipantRate($meeting, $member),
            ])->all(),
        ]);
    }

    public function update(Request $request, Meeting $meeting, MeetingCostCalculator $costCalculator): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $request->merge([
            'duration_minutes' => $request->input('duration_minutes', $meeting->duration_minutes ?: 60),
            'organizer_id' => $request->input('organizer_id', $meeting->organizer_id ?? Auth::id()),
            'priority' => $request->input('priority', $meeting->priority ?? 'normal'),
            'recurrence_type' => $request->input('recurrence_type', $meeting->recurrence_type ?? 'single'),
        ]);

        $validated = $this->validateMeeting($request);
        $requestedIds = collect($validated['participants'] ?? []);
        $participantUserIds = $this->resolveParticipantUserIds($requestedIds);
        $attendance = $validated['attendance'] ?? [];
        $roles = $validated['participant_roles'] ?? [];
        $invitations = $validated['invitations'] ?? [];
        $confirmations = $validated['confirmations'] ?? [];
        unset($validated['attendance'], $validated['participants'], $validated['participant_roles'], $validated['invitations'], $validated['confirmations']);

        DB::transaction(function () use ($meeting, $validated, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations, $costCalculator): void {
            $meeting->update($validated);
            $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations, $costCalculator);
        });

        return redirect()->route('meetings.index')->with('success', 'Reunião atualizada com sucesso.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermission('meetings.delete'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting->update(['status' => 'canceled']);

        return redirect()->route('meetings.index')->with('success', 'Reunião removida com sucesso.');
    }

    public function cancel(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);
        abort_if($meeting->status === 'completed', 422, 'Uma reunião concluída não pode ser cancelada.');

        $meeting->update(['status' => 'canceled']);

        return redirect()->route('meetings.index')->with('success', 'Reunião cancelada.');
    }

    public function storeActionItem(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'responsible_user_id' => [
                'nullable', 'integer',
                Rule::exists('meeting_participants', 'user_id')->where('meeting_id', $meeting->id),
            ],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'due_date' => ['nullable', 'date'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'requires_follow_up' => ['sometimes', 'boolean'],
            'dependencies' => ['nullable', 'array'],
            'dependencies.*' => [
                'integer',
                Rule::exists('meeting_action_items', 'id')->where('meeting_id', $meeting->id),
            ],
        ]);

        $dependencies = collect($validated['dependencies'] ?? [])->map(fn ($id): int => (int) $id)->unique();
        unset($validated['dependencies']);

        $actionItem = $meeting->actionItems()->create($validated + ['status' => 'open']);
        $actionItem->dependencies()->sync($dependencies->all());

        return back()->with('success', 'Encaminhamento cadastrado.');
    }

    public function updateActionItem(Request $request, Meeting $meeting, MeetingActionItem $actionItem): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);
        abort_unless($actionItem->meeting_id === $meeting->id, 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_progress', 'blocked', 'completed', 'canceled'])],
            'delivered_result' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'requires_follow_up' => ['sometimes', 'boolean'],
        ]);

        $validated['requires_follow_up'] = $request->boolean('requires_follow_up');
        $validated['completed_at'] = $validated['status'] === 'completed' ? ($actionItem->completed_at ?? now()) : null;
        $actionItem->update($validated);

        return back()->with('success', 'Encaminhamento atualizado.');
    }

    public function storeDecision(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'decided_by' => [
                'nullable', 'integer',
                Rule::exists('meeting_participants', 'user_id')->where('meeting_id', $meeting->id),
            ],
            'decided_at' => ['nullable', 'date'],
        ]);

        $meeting->decisions()->create($validated + ['decided_at' => now()]);

        return back()->with('success', 'Decisão registrada.');
    }

    public function saveConclusion(Request $request, Meeting $meeting): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);

        $validated = $request->validate([
            'summary' => ['nullable', 'string'],
            'outcome' => ['nullable', 'string'],
        ]);

        $meeting->conclusion()->updateOrCreate([], [
            ...$validated,
            'recorded_by' => $request->user()->id,
            'concluded_at' => now(),
        ]);

        return back()->with('success', 'Conclusão registrada.');
    }

    /** @param array<string, mixed> $preflight
     */
    private function storeAiAnalysis(Meeting $meeting, int $userId, string $stage, array $preflight): void
    {
        $metadata = $preflight['ai_metadata'] ?? [];
        $result = $preflight['ai_result'] ?? null;
        $version = (int) $meeting->analyses()->where('analysis_type', $stage)->max('version') + 1;
        $meeting->analyses()->create([
            'requested_by' => $userId,
            'analysis_type' => $stage,
            'version' => $version,
            'prompt_version' => $metadata['prompt_version'] ?? 'meeting-insights-v1',
            'transcript' => '',
            'input_data' => $preflight['ai_input_data'] ?? [],
            'result' => $result,
            'summary' => $result['summary'] ?? null,
            'decisions' => $result['findings'] ?? null,
            'action_items' => collect($result['recommendations'] ?? [])->map(fn (array $recommendation): array => [
                'description' => trim(($recommendation['title'] ?? '').': '.($recommendation['detail'] ?? '')),
                'responsible_hint' => 'Não informado',
                'deadline_hint' => 'Não informado',
                'priority' => 'normal',
            ])->values()->all(),
            'provider' => $metadata['provider'] ?? config('services.ai.provider', 'gemini'),
            'model' => $metadata['model'] ?? app(MeetingAiService::class)->configuredModel(),
            'response_id' => $metadata['response_id'] ?? null,
            'usage' => $metadata['usage'] ?? null,
            'provider_cost' => is_numeric(data_get($metadata, 'usage.cost')) ? data_get($metadata, 'usage.cost') : null,
            'status' => $result === null ? 'failed' : 'completed',
            'error_message' => $preflight['ai_error'] ?? null,
            'completed_at' => $result === null ? null : now(),
        ]);
    }

    /** @param array<string, mixed> $aiResponse
     */
    private function updateAnalysisFromAi(MeetingAnalysis $analysis, array $aiResponse): void
    {
        $result = $aiResponse['result'];
        $analysis->update([
            'result' => $result,
            'summary' => $result['summary'],
            'decisions' => $result['findings'],
            'action_items' => collect($result['recommendations'])->map(fn (array $recommendation): array => [
                'description' => trim($recommendation['title'].': '.$recommendation['detail']),
                'responsible_hint' => 'Não informado',
                'deadline_hint' => 'Não informado',
                'priority' => 'normal',
            ])->values()->all(),
            'provider' => $aiResponse['provider'],
            'model' => $aiResponse['model'],
            'response_id' => $aiResponse['response_id'],
            'prompt_version' => $aiResponse['prompt_version'],
            'usage' => $aiResponse['usage'],
            'provider_cost' => is_numeric(data_get($aiResponse, 'usage.cost')) ? data_get($aiResponse, 'usage.cost') : null,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function buildMeetingAnalysisContext(Meeting $meeting, string $stage, string $notes): array
    {
        $participants = $meeting->meetingParticipants->map(fn ($participant): array => [
            'cargo' => $participant->user?->jobPosition?->name ?? 'Não informado',
            'papel' => $participant->role,
            'presenca' => $participant->attendance_status,
            'participante_efetivo' => $participant->attendance_status === 'confirmed',
        ]);
        $decisions = $meeting->decisions->map(fn ($decision): array => [
            'titulo' => $decision->title,
            'descricao' => $decision->description,
            'responsavel_registrado' => $decision->decided_by !== null,
            'data' => $decision->decided_at?->toIso8601String(),
        ]);
        $actions = $meeting->actionItems->map(fn ($item): array => [
            'titulo' => $item->title,
            'descricao' => $item->description,
            'status' => $item->status,
            'prazo' => $item->due_date?->toDateString(),
            'responsavel_atribuido' => $item->responsible_user_id !== null,
            'resultado_entregue' => $item->delivered_result,
        ]);
        $elapsedSeconds = (int) ($meeting->actual_duration_seconds ?? 0);
        if ($meeting->timer_started_at !== null) {
            $elapsedSeconds += max(0, now()->diffInSeconds($meeting->timer_started_at, absolute: true));
        }

        $terms = collect(preg_split('/\s+/u', Str::lower($meeting->title)) ?: [])
            ->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique()->take(5);
        $historicalMeetings = Meeting::query()->where('user_id', $meeting->user_id)
            ->where('id', '<>', $meeting->id)->where('status', 'completed')
            ->when($terms->isNotEmpty(), function ($query) use ($terms): void {
                $query->where(function ($builder) use ($terms): void {
                    foreach ($terms as $term) {
                        $builder->orWhere('title', 'like', "%{$term}%")
                            ->orWhere('objective', 'like', "%{$term}%");
                    }
                });
            })
            ->with(['conclusion', 'actionItems'])->latest('date')->limit(5)->get()
            ->map(fn (Meeting $past): array => [
                'titulo' => $past->title,
                'objetivo' => $past->objective,
                'resultado' => $past->conclusion?->outcome ?? $past->conclusion?->summary,
                'duracao_real_segundos' => $past->actual_duration_seconds,
                'encaminhamentos_concluidos' => $past->actionItems->where('status', 'completed')->count(),
                'encaminhamentos_total' => $past->actionItems->count(),
            ]);
        $financialMetrics = [];
        if ($stage === 'pre_meeting' && Auth::user()?->canViewCosts()) {
            $estimatedCost = app(MeetingCostCalculator::class)->estimatedTotal($meeting);
            $financialMetrics = [
                'custo_estimado_backend' => $estimatedCost,
                'horas_colaborador' => $participants->count() * ((int) $meeting->duration_minutes / 60),
                'duracao_reuniao_horas' => (int) $meeting->duration_minutes / 60,
            ];
        }
        $weekStart = $meeting->date?->copy()->startOfWeek()->toDateString();
        $weekEnd = $meeting->date?->copy()->endOfWeek()->toDateString();
        $weeklyAgenda = Meeting::query()->where('user_id', $meeting->user_id)
            ->whereIn('status', ['scheduled', 'ongoing'])
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->whereHas('users', fn ($query) => $query->whereIn('users.id', $meeting->meetingParticipants->pluck('user_id')))
            ->get(['id', 'duration_minutes']);

        return [
            'etapa' => $stage,
            'reuniao' => [
                'titulo' => $meeting->title,
                'categoria' => $meeting->category,
                'objetivo' => $meeting->objective,
                'contexto' => $meeting->description,
                'pauta' => $meeting->agenda,
                'resultado_esperado' => $meeting->expected_outcome,
                'data' => $meeting->date?->toDateString(),
                'duracao_prevista_minutos' => $meeting->duration_minutes,
                'duracao_real_segundos' => $elapsedSeconds,
                'recorrencia' => [$meeting->recurrence_type, $meeting->recurrence_frequency, $meeting->recurrence_interval],
            ],
            'participantes' => $participants->values(),
            'anotacoes_manuais_desta_execucao' => $notes,
            'decisoes_registradas' => $decisions->values(),
            'encaminhamentos_registrados' => $actions->values(),
            'assuntos_pendentes' => $actions->whereNotIn('status', ['completed', 'canceled'])->values(),
            'indicadores_financeiros_calculados_pelo_sistema' => $financialMetrics,
            'impacto_agenda_semanal' => [
                'reunioes_com_participantes' => $weeklyAgenda->count(),
                'horas_agendadas' => (float) $weeklyAgenda->sum('duration_minutes') / 60,
            ],
            'resultado_informado' => [
                'resumo' => $meeting->conclusion?->summary,
                'resultado' => $meeting->conclusion?->outcome,
            ],
            'avaliacoes_dos_participantes' => null,
            'historico_relacionado_acessivel' => $historicalMeetings->values(),
            'historico_suficiente_para_estimativa' => $historicalMeetings->count() >= 3,
            'observacao' => 'Nomes pessoais e conteúdo de outras reuniões fora do escopo acessível não são enviados. Sugestões exigem revisão humana.',
        ];
    }

    private function preflightCacheKey(int $userId, string $token): string
    {
        return "meeting-preflight:{$userId}:{$token}";
    }

    private function assertCanManageMeeting(Request $request, Meeting $meeting): void
    {
        abort_unless($request->user()->hasPermission('meetings.edit'), 403);
        abort_unless($meeting->user_id === $request->user()->id, 403);
    }

    private function syncParticipants(
        Meeting $meeting,
        Collection $requestedIds,
        array $userIds,
        array $attendance,
        array $roles,
        array $invitations,
        array $confirmations,
        MeetingCostCalculator $costCalculator,
    ): void {
        $pivot = collect($userIds)->mapWithKeys(fn (int $userId): array => [$userId => [
            'role' => $roles[$userId] ?? ($userId === (int) $meeting->organizer_id ? 'organizer' : 'participant'),
            'invitation_status' => $invitations[$userId] ?? 'sent',
            'confirmation_status' => $confirmations[$userId] ?? 'pending',
            'attendance_status' => $attendance[$userId] ?? 'pending',
        ]])->all();

        $meeting->users()->sync($pivot);

        $legacyParticipants = Participant::whereIn('member_user_id', $userIds)
            ->orWhereKey($requestedIds)
            ->get(['id', 'member_user_id']);

        $meeting->participants()->sync($legacyParticipants->mapWithKeys(fn (Participant $participant): array => [
            $participant->id => [
                'attendance_status' => $attendance[$participant->member_user_id] ?? $attendance[$participant->id] ?? 'pending',
            ],
        ])->all());

        $meeting->meetingParticipants()->with('user.jobPosition', 'user.team', 'user.directorate', 'user.participantProfile', 'costSnapshot')
            ->get()->each(function ($participant) use ($costCalculator): void {
                if ($participant->costSnapshot !== null) {
                    return;
                }

                $participant->costSnapshot()->create([
                    'job_position_id' => $participant->user?->job_position_id,
                    'team_id' => $participant->user?->team_id,
                    'directorate_id' => $participant->user?->directorate_id,
                    'hourly_rate' => $participant->user ? $costCalculator->userRate($participant->user) : 0,
                    'captured_at' => now(),
                ]);
            });
    }

    /** @return Collection<int, User> */
    private function availableMembers(): Collection
    {
        return User::with(['jobPosition', 'participantProfile'])
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->where('role', '<>', 'admin')->orWhereKey(Auth::id());
            })
            ->orderBy('name')
            ->get();
    }

    /** @return array<string, array<int, mixed>> */
    private function meetingRules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:100'],
            'objective' => ['nullable', 'string'],
            'organizer_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:time'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_format' => ['nullable', Rule::in(['in_person', 'virtual', 'hybrid'])],
            'agenda' => ['nullable', 'string'],
            'expected_outcome' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'recurrence_type' => ['required', Rule::in(['single', 'recurring'])],
            'recurrence_frequency' => ['nullable', 'required_if:recurrence_type,recurring', Rule::in(['daily', 'weekly', 'monthly', 'yearly'])],
            'recurrence_interval' => ['nullable', 'required_if:recurrence_type,recurring', 'integer', 'min:1', 'max:52'],
            'recurrence_ends_at' => ['nullable', 'date', 'after_or_equal:date'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'ongoing', 'completed', 'canceled'])],
            'notes' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! User::whereKey($value)->exists() && ! Participant::whereKey($value)->exists()) {
                    $fail('Selecione um usuário válido para a reunião.');
                }
            }],
            'participant_roles' => ['nullable', 'array'],
            'participant_roles.*' => [Rule::in(['organizer', 'participant', 'decision_responsible', 'guest'])],
            'invitations' => ['nullable', 'array'],
            'invitations.*' => [Rule::in(['pending', 'sent', 'accepted', 'declined'])],
            'confirmations' => ['nullable', 'array'],
            'confirmations.*' => [Rule::in(['pending', 'confirmed', 'declined'])],
            'attendance' => ['nullable', 'array'],
            'attendance.*' => [Rule::in(['pending', 'confirmed', 'absent'])],
        ];
    }

    private function validateMeeting(Request $request): array
    {
        return $request->validate($this->meetingRules());
    }

    /** @return array<int, int> */
    private function resolveParticipantUserIds(Collection $requestedIds): array
    {
        $userIds = User::whereKey($requestedIds)->where('role', '<>', 'admin')->pluck('id');
        $legacyUserIds = Participant::whereKey($requestedIds)->whereNotNull('member_user_id')->pluck('member_user_id');

        return $userIds->merge($legacyUserIds)->map(fn ($id): int => (int) $id)->unique()->values()->all();
    }
}
