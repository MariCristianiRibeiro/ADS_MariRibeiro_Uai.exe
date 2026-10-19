<?php

namespace App\Http\Controllers;

use App\Models\AccessProfile;
use App\Models\JobPosition;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('participants.view'), 403);

        $query = Participant::with(['user.supervisors', 'memberUser.team.directorate'])
            ->where('user_id', Auth::id())
            ->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $searchTerm = '%'.mb_strtolower($search).'%';
                $q->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$searchTerm]);
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $participants = $query->get();

        return view('participants.index', [
            'participants' => $participants,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasPermission('participants.create'), 403);

        return view('participants.create', [
            'teams' => $this->activeTeams(),
            'jobPositions' => JobPosition::where('active', true)->orderBy('name')->get(),
            'accessProfiles' => AccessProfile::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', 'guest')]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:participants,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('active', true)],
            'job_position_id' => ['nullable', 'integer', Rule::exists('job_positions', 'id')->where('active', true)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
        if ($request->user()->can('manage-users')) {
            $rules['profile_ids'] = ['nullable', 'array'];
            $rules['profile_ids.*'] = ['integer', 'distinct', Rule::exists('access_profiles', 'id')->where('active', true)];
        } else {
            $rules['profile_ids'] = ['prohibited'];
        }
        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated): void {
            $team = isset($validated['team_id']) ? Team::findOrFail($validated['team_id']) : null;

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'participant',
                'status' => $validated['status'],
                'hourly_rate' => $validated['hourly_rate'] ?? null,
                'team_id' => $team?->id,
                'directorate_id' => $team?->directorate_id,
                'job_position_id' => $validated['job_position_id'] ?? null,
            ]);
            if (! empty($validated['profile_ids'])) {
                $user->accessProfiles()->sync($validated['profile_ids']);
                Log::notice('Access profiles assigned to participant account', ['actor_id' => Auth::id(), 'user_id' => $user->id, 'profile_ids' => $validated['profile_ids']]);
            }

            Participant::create([
                ...collect($validated)->except('password', 'team_id', 'job_position_id')->all(),
                'user_id' => Auth::id(),
                'member_user_id' => $user->id,
                'role' => $user->role,
            ]);
        });

        return redirect()->route('participants.index')->with('success', 'Participante cadastrado com sucesso.');
    }

    public function edit(Participant $participant): View
    {
        abort_unless(auth()->user()->hasPermission('participants.edit'), 403);

        $participant->load('memberUser.team');

        return view('participants.edit', [
            'participant' => $participant,
            'members' => User::where('status', 'active')->where('role', '<>', 'admin')->orderBy('name')->get(),
            'teams' => $this->activeTeams(),
            'jobPositions' => JobPosition::where('active', true)->orWhere('id', $participant->memberUser?->job_position_id)->orderBy('name')->get(),
            'accessProfiles' => AccessProfile::where('active', true)
                ->when($participant->memberUser, fn ($query) => $query->orWhereIn('id', $participant->memberUser->accessProfiles()->select('access_profiles.id')))
                ->orderBy('name')->get(),
            'selectedProfileIds' => $participant->memberUser?->accessProfiles->modelKeys() ?? [],
        ]);
    }

    public function update(Request $request, Participant $participant): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('participants.edit'), 403);

        $request->merge(['type' => $request->input('type', $participant->type ?? 'guest')]);

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email,'.$participant->id],
            'member_user_id' => ['nullable', 'integer', 'required_with:team_id', Rule::exists('users', 'id')->whereNot('role', 'admin')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('active', true)],
            'job_position_id' => ['nullable', 'integer', Rule::exists('job_positions', 'id')->where(function ($query) use ($participant) {
                $query->where('active', true)->orWhere('id', $participant->memberUser?->job_position_id);
            })],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
        if ($request->user()->can('manage-users')) {
            $rules['profile_ids'] = ['nullable', 'array'];
            $rules['clear_profiles'] = ['sometimes', 'boolean'];
            $rules['profile_ids.*'] = ['integer', 'distinct', Rule::exists('access_profiles', 'id')->where(function ($query) use ($participant) {
                $query->where('active', true)->orWhereIn('id', $participant->memberUser?->accessProfiles()->select('access_profiles.id') ?? []);
            })];
        } else {
            $rules['profile_ids'] = ['prohibited'];
            $rules['clear_profiles'] = ['prohibited'];
        }
        $validated = $request->validate($rules);

        $validated['role'] = ($validated['member_user_id'] ?? null)
            ? User::findOrFail($validated['member_user_id'])->role
            : null;
        if (! empty($validated['profile_ids']) && (int) ($validated['member_user_id'] ?? 0) === Auth::id()) {
            throw ValidationException::withMessages(['profile_ids' => 'Você não pode alterar seus próprios perfis de acesso.']);
        }

        DB::transaction(function () use ($participant, $validated): void {
            $team = isset($validated['team_id']) ? Team::findOrFail($validated['team_id']) : null;
            $memberUserId = $validated['member_user_id'] ?? null;

            $participant->update(collect($validated)->except('team_id', 'job_position_id')->all());

            if ($memberUserId !== null) {
                $memberUser = User::whereKey($memberUserId)->firstOrFail();
                $memberUser->update([
                    'hourly_rate' => $validated['hourly_rate'] ?? null,
                    'team_id' => $team?->id,
                    'directorate_id' => $team?->directorate_id,
                    'job_position_id' => $validated['job_position_id'] ?? null,
                ]);
                if (! empty($validated['clear_profiles'])) {
                    $memberUser->accessProfiles()->detach();
                    Log::notice('Access profiles removed from participant account', ['actor_id' => Auth::id(), 'user_id' => $memberUser->id]);
                } elseif (! empty($validated['profile_ids'])) {
                    $memberUser->accessProfiles()->sync($validated['profile_ids']);
                    Log::notice('Access profiles updated for participant account', ['actor_id' => Auth::id(), 'user_id' => $memberUser->id, 'profile_ids' => $validated['profile_ids']]);
                }
            }
        });

        return redirect()->route('participants.index')->with('success', 'Participante atualizado com sucesso.');
    }

    public function destroy(Participant $participant): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('participants.delete'), 403);

        $participant->delete();

        return redirect()->route('participants.index')->with('success', 'Participante removido com sucesso.');
    }

    private function activeTeams(): Collection
    {
        return Team::query()
            ->with('directorate')
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }
}
