<?php

namespace App\Http\Controllers;

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'members' => User::with(['jobPosition', 'participantProfile'])->where('status', 'active')->where('role', '<>', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.create'), 403);

        $request->merge(['duration_minutes' => $request->input('duration_minutes', 60)]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'actual_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:scheduled,ongoing,completed,canceled'],
            'notes' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer'],
            'attendance' => ['nullable', 'array'],
            'attendance.*' => ['required', 'in:pending,confirmed,absent'],
        ]);

        $requestedIds = collect($validated['participants'] ?? []);
        $userIds = User::whereKey($requestedIds)->where('role', '<>', 'admin')->pluck('id');
        $legacyUserIds = Participant::whereKey($requestedIds)->whereNotNull('member_user_id')->pluck('member_user_id');
        $participantUserIds = $userIds->merge($legacyUserIds)->unique()->values()->all();
        $attendance = $validated['attendance'] ?? [];
        unset($validated['attendance'], $validated['participants']);

        $validated['user_id'] = Auth::id();

        $meeting = Meeting::create($validated);
        $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance);

        return redirect()->route('meetings.index')->with('success', 'Reunião cadastrada com sucesso.');
    }

    public function edit(Meeting $meeting): View
    {
        abort_unless(Auth::user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting->load('users');

        return view('meetings.edit', [
            'meeting' => $meeting,
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(),
            'members' => User::with(['jobPosition', 'participantProfile'])->where('status', 'active')->where('role', '<>', 'admin')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('meetings.edit'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $request->merge(['duration_minutes' => $request->input('duration_minutes', $meeting->duration_minutes ?: 60)]);
        $request->merge(['actual_duration_seconds' => $request->input('actual_duration_seconds', $meeting->actual_duration_seconds)]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'date' => ['required', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'actual_duration_seconds' => ['nullable', 'integer', 'min:0'],
            'location' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:scheduled,ongoing,completed,canceled'],
            'notes' => ['nullable', 'string'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer'],
            'attendance' => ['nullable', 'array'],
            'attendance.*' => ['required', 'in:pending,confirmed,absent'],
        ]);

        $requestedIds = collect($validated['participants'] ?? []);
        $userIds = User::whereKey($requestedIds)->where('role', '<>', 'admin')->pluck('id');
        $legacyUserIds = Participant::whereKey($requestedIds)->whereNotNull('member_user_id')->pluck('member_user_id');
        $participantUserIds = $userIds->merge($legacyUserIds)->unique()->values()->all();
        $attendance = $validated['attendance'] ?? [];
        unset($validated['attendance'], $validated['participants']);

        $meeting->update($validated);
        $this->syncParticipants($meeting, $requestedIds, $participantUserIds, $attendance);

        return redirect()->route('meetings.index')->with('success', 'Reunião atualizada com sucesso.');
    }

    public function destroy(Meeting $meeting): RedirectResponse
    {
        abort_unless(Auth::user()->hasPermission('meetings.delete'), 403);

        abort_unless($meeting->user_id === Auth::id(), 403);

        $meeting->delete();

        return redirect()->route('meetings.index')->with('success', 'Reunião removida com sucesso.');
    }

    private function syncParticipants(Meeting $meeting, Collection $requestedIds, array $userIds, array $attendance): void
    {
        $meeting->users()->sync(collect($userIds)->mapWithKeys(
            fn (int $userId) => [$userId => ['attendance_status' => $attendance[$userId] ?? 'pending']],
        )->all());

        $legacyParticipants = Participant::whereIn('member_user_id', $userIds)
            ->orWhereKey($requestedIds)
            ->get(['id', 'member_user_id']);

        $meeting->participants()->sync($legacyParticipants->mapWithKeys(
            fn (Participant $participant) => [$participant->id => [
                'attendance_status' => $attendance[$participant->member_user_id] ?? $attendance[$participant->id] ?? 'pending',
            ]],
        )->all());
    }
}
