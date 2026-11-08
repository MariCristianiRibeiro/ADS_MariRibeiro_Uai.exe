<?php

namespace App\Http\Controllers;

use App\Models\Directorate;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\Participant;
use App\Models\Project;
use App\Models\User;
use App\Services\MeetingTranscriptAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    public function create(): View
    {
        abort_unless(Auth::user()->hasPermission('meetings.create'), 403);

        return view('meetings.create', [
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => $this->availableMembers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.create'), 403);

        $request->merge([
            'duration_minutes' => $request->input('duration_minutes', 60),
            'organizer_id' => $request->input('organizer_id', Auth::id()),
            'priority' => $request->input('priority', 'normal'),
            'recurrence_type' => $request->input('recurrence_type', 'single'),
        ]);

        $validated = $this->validateMeeting($request);
        $requestedIds = collect($validated['participants'] ?? []);
        $participantUserIds = $this->resolveParticipantUserIds($requestedIds);
        $attendance = $validated['attendance'] ?? [];
        $roles = $validated['participant_roles'] ?? [];
        $invitations = $validated['invitations'] ?? [];
        $confirmations = $validated['confirmations'] ?? [];
        unset($validated['attendance'], $validated['participants'], $validated['participant_roles'], $validated['invitations'], $validated['confirmations']);

        $meeting = DB::transaction(function () use ($validated, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations): Meeting {
            $meeting = Meeting::create([...$validated, 'user_id' => Auth::id()]);
            $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations);

            return $meeting;
        });

        return redirect()->route('meetings.index')->with('success', 'Reunião cadastrada com sucesso.');
    }

    public function show(Meeting $meeting, Request $request): View
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
            'openAiConfigured' => filled(config('services.openai.api_key')),
        ]);
    }

    public function analyzeTranscript(Request $request, Meeting $meeting, MeetingTranscriptAnalyzer $analyzer): RedirectResponse
    {
        $this->assertCanManageMeeting($request, $meeting);

        $validated = $request->validate([
            'transcript' => ['required', 'string', 'min:20', 'max:100000'],
        ]);

        if (blank(config('services.openai.api_key'))) {
            return back()->withErrors([
                'transcript' => 'A integração ChatGPT ainda não está configurada. Defina OPENAI_API_KEY no servidor.',
            ])->withInput();
        }

        $analysis = $meeting->analyses()->create([
            'requested_by' => $request->user()->id,
            'transcript' => $validated['transcript'],
            'provider' => 'openai',
            'model' => config('services.openai.model'),
            'status' => 'processing',
        ]);

        try {
            $result = $analyzer->analyze($validated['transcript']);
            $analysis->update([...$result, 'status' => 'completed', 'completed_at' => now()]);
        } catch (\Throwable $exception) {
            $analysis->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'transcript' => 'Não foi possível concluir a análise agora. Tente novamente mais tarde.',
            ])->withInput();
        }

        return back()->with('success', 'Transcrição analisada pelo ChatGPT. Revise as sugestões antes de cadastrá-las.');
    }

    public function edit(Meeting $meeting): View
    {
        abort_unless(Auth::user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting->load(['users', 'meetingParticipants.costSnapshot']);

        return view('meetings.edit', [
            'meeting' => $meeting,
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => $this->availableMembers(),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $request->merge([
            'duration_minutes' => $request->input('duration_minutes', $meeting->duration_minutes ?: 60),
            'organizer_id' => $request->input('organizer_id', $meeting->organizer_id ?? Auth::id()),
            'actual_duration_seconds' => $request->input('actual_duration_seconds', $meeting->actual_duration_seconds),
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

        DB::transaction(function () use ($meeting, $validated, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations): void {
            $meeting->update($validated);
            $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance, $roles, $invitations, $confirmations);
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
            ->get()->each(function ($participant): void {
                if ($participant->costSnapshot !== null) {
                    return;
                }

                $participant->costSnapshot()->create([
                    'job_position_id' => $participant->user?->job_position_id,
                    'team_id' => $participant->user?->team_id,
                    'directorate_id' => $participant->user?->directorate_id,
                    'hourly_rate' => $participant->user?->jobPosition?->hourly_rate
                        ?? $participant->user?->hourly_rate
                        ?? $participant->user?->participantProfile?->hourly_rate
                        ?? 0,
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
            'actual_duration_seconds' => ['nullable', 'integer', 'min:0'],
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
