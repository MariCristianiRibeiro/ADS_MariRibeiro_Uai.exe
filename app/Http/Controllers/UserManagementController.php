<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Directorate;
use App\Models\JobPosition;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::with(['supervisors' => fn ($query) => $query->orderBy('name')])->latest();

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($query) use ($search) {
                $query->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        $users = $query->get();

        return view('users.index', [
            'users' => $users,
            'search' => $request->search ?? '',
            'statusFilter' => $request->status ?? 'all',
            'roleFilter' => $request->role ?? 'all',
        ]);
    }

    public function create(): View
    {
        return view('users.create', $this->structureOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['required', 'in:active,inactive,pending,pending_finance'],
            'role' => ['required', 'in:admin,hr,executive,director,director_commercial,director_network,manager,participant'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'job_position_id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'supervisor_ids' => ['nullable', 'array', 'max:2'],
            'supervisor_ids.*' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $supervisorIds = collect($validated['supervisor_ids'] ?? [])->unique()->values();
        $this->validateSupervisors($validated['role'], $supervisorIds);

        $user = User::create([
            ...collect($validated)->except('supervisor_ids', 'password')->all(),
            'password' => Hash::make($validated['password']),
        ]);

        $this->syncSupervisors($user, $supervisorIds);

        return redirect()->route('users.index')->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'supervisors' => $user->supervisors,
            ...$this->structureOptions($user->id),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'status' => ['required', 'in:active,inactive,pending,pending_finance'],
            'role' => ['required', 'in:admin,hr,executive,director,director_commercial,director_network,manager,participant'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'job_position_id' => ['nullable', 'integer', 'exists:job_positions,id'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_ids' => ['nullable', 'array', 'max:2'],
            'supervisor_ids.*' => ['nullable', 'integer', 'exists:users,id', 'different:' . $user->id],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $supervisorIds = collect($validated['supervisor_ids'] ?? [])->unique()->values();
        $this->validateSupervisors($validated['role'], $supervisorIds, $user->id);
        $user->update(collect($validated)->except('supervisor_ids')->all());
        $this->syncSupervisors($user, $supervisorIds);

        return redirect()->route('users.index')->with('success', 'Usuário atualizado com sucesso.');
    }

    private function structureOptions(?int $exceptUserId = null): array
    {
        return [
            'availableSupervisors' => User::when($exceptUserId, fn ($query) => $query->where('id', '<>', $exceptUserId))
                ->whereIn('role', ['executive', 'director', 'director_commercial', 'director_network', 'manager'])
                ->where('status', 'active')
                ->orderBy('name')
                ->get(),
            'directorates' => Directorate::where('active', true)->orderBy('name')->get(),
            'teams' => Team::where('active', true)->orderBy('name')->get(),
            'jobPositions' => JobPosition::where('active', true)->orderBy('name')->get(),
        ];
    }

    private function validateSupervisors(string $role, $supervisorIds, ?int $userId = null): void
    {
        if ($role === 'manager' && $supervisorIds->isEmpty()) {
            throw ValidationException::withMessages(['supervisor_ids' => 'Gestores precisam ter pelo menos um Diretor responsável.']);
        }

        $allowed = match ($role) {
            'director', 'director_commercial' => ['executive', 'director', 'director_commercial'],
            'director_network' => ['director_commercial'],
            'manager' => ['director_network'],
            'participant' => ['manager', 'director_network'],
            default => [],
        };
        $supervisors = User::whereKey($supervisorIds)->get();
        if ($supervisors->contains(fn (User $supervisor) => $userId === $supervisor->id || ! in_array($supervisor->role, $allowed, true))) {
            throw ValidationException::withMessages(['supervisor_ids' => 'Os superiores selecionados não são compatíveis com este perfil.']);
        }
    }

    private function syncSupervisors(User $user, $supervisorIds): void
    {
        $supervisors = User::whereKey($supervisorIds)->get();
        $relationships = $supervisors->mapWithKeys(fn (User $supervisor) => [
            $supervisor->id => ['relationship_type' => match ([$user->role, $supervisor->role]) {
                ['director', 'executive'] => 'executive_director',
                ['director_commercial', 'executive'] => 'executive_director_commercial',
                ['director', 'director'], ['director_commercial', 'director'] => 'director_director',
                ['director_network', 'director_commercial'] => 'commercial_director_network',
                ['manager', 'director_network'] => 'network_director_manager',
                ['participant', 'manager'] => 'manager_collaborator',
                ['participant', 'director_network'] => 'network_director_collaborator',
                default => 'hierarchical_supervision',
            }],
        ])->all();
        $user->supervisors()->sync($relationships);
    }
}
