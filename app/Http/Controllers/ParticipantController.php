<?php

namespace App\Http\Controllers;

use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ParticipantController extends Controller
{
    public function index(Request $request): View
    {
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
        return view('participants.create', [
            'teams' => $this->activeTeams(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', 'guest')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email', 'unique:participants,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('active', true)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

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
            ]);

            Participant::create([
                ...collect($validated)->except('password', 'team_id')->all(),
                'user_id' => Auth::id(),
                'member_user_id' => $user->id,
                'role' => $user->role,
            ]);
        });

        return redirect()->route('participants.index')->with('success', 'Participante cadastrado com sucesso.');
    }

    public function edit(Participant $participant): View
    {
        $participant->load('memberUser.team');

        return view('participants.edit', [
            'participant' => $participant,
            'members' => User::where('status', 'active')->where('role', '<>', 'admin')->orderBy('name')->get(),
            'teams' => $this->activeTeams(),
        ]);
    }

    public function update(Request $request, Participant $participant): RedirectResponse
    {
        $request->merge(['type' => $request->input('type', $participant->type ?? 'guest')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:participants,email,'.$participant->id],
            'member_user_id' => ['nullable', 'integer', 'required_with:team_id', Rule::exists('users', 'id')->whereNot('role', 'admin')],
            'team_id' => ['nullable', 'integer', Rule::exists('teams', 'id')->where('active', true)],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', 'in:active,inactive,pending'],
            'type' => ['required', 'in:internal,external,guest'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $validated['role'] = ($validated['member_user_id'] ?? null)
            ? User::findOrFail($validated['member_user_id'])->role
            : null;

        DB::transaction(function () use ($participant, $validated): void {
            $team = isset($validated['team_id']) ? Team::findOrFail($validated['team_id']) : null;
            $memberUserId = $validated['member_user_id'] ?? null;

            $participant->update(collect($validated)->except('team_id')->all());

            if ($memberUserId !== null) {
                User::whereKey($memberUserId)->update([
                    'hourly_rate' => $validated['hourly_rate'] ?? null,
                    'team_id' => $team?->id,
                    'directorate_id' => $team?->directorate_id,
                ]);
            }
        });

        return redirect()->route('participants.index')->with('success', 'Participante atualizado com sucesso.');
    }

    public function destroy(Participant $participant): RedirectResponse
    {
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
