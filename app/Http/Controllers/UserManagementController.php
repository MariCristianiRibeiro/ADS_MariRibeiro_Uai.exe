<?php

namespace App\Http\Controllers;

use App\Models\AccessProfile;
use App\Models\Directorate;
use App\Models\JobPosition;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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
                $term = '%'.mb_strtolower($search).'%';
                $query->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'status' => ['required', 'in:active,inactive,pending,pending_finance'],
            'role' => ['required', 'in:admin,hr,executive,director,director_commercial,director_network,manager,participant'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'job_position_id' => ['nullable', 'integer', Rule::exists('job_positions', 'id')->where('active', true)],
            'profile_ids' => ['nullable', 'array'],
            'profile_ids.*' => ['integer', 'distinct', Rule::exists('access_profiles', 'id')->where('active', true)],
            'supervisor_ids' => ['nullable', 'array', 'max:2'],
            'supervisor_ids.*' => ['nullable', 'integer', 'exists:users,id'],
        ];

        if ($request->input('role') !== 'admin') {
            $rules['email'][] = Rule::unique('participants', 'email');
        }

        $validated = $request->validate($rules);

        $supervisorIds = collect($validated['supervisor_ids'] ?? [])->unique()->values();
        $this->validateSupervisors($validated['role'], $supervisorIds);

        DB::transaction(function () use ($validated, $supervisorIds): void {
            $user = User::create([
                ...collect($validated)->except('supervisor_ids', 'profile_ids', 'password')->all(),
                'password' => Hash::make($validated['password']),
            ]);

            $this->syncSupervisors($user, $supervisorIds);
            $user->accessProfiles()->sync($validated['profile_ids'] ?? []);
            if (! empty($validated['profile_ids'])) {
                Log::notice('Access profiles assigned to user', ['actor_id' => auth()->id(), 'user_id' => $user->id, 'profile_ids' => $validated['profile_ids']]);
            }

            if ($user->role !== 'admin') {
                $user->participants()->create([
                    'member_user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status === 'pending_finance' ? 'pending' : $user->status,
                    'role' => $user->role,
                ]);
            }
        });

        return redirect()->route('users.index')->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user,
            'supervisors' => $user->supervisors,
            'selectedProfileIds' => $user->accessProfiles->modelKeys(),
            ...$this->structureOptions($user->id),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'status' => ['required', 'in:active,inactive,pending,pending_finance'],
            'role' => ['required', 'in:admin,hr,executive,director,director_commercial,director_network,manager,participant'],
            'directorate_id' => ['nullable', 'integer', 'exists:directorates,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'job_position_id' => ['nullable', 'integer', Rule::exists('job_positions', 'id')->where(function ($query) use ($user) {
                $query->where('active', true)->orWhere('id', $user->job_position_id);
            })],
            'profile_ids' => ['nullable', 'array'],
            'clear_profiles' => ['sometimes', 'boolean'],
            'profile_ids.*' => ['integer', 'distinct', Rule::exists('access_profiles', 'id')->where(function ($query) use ($user) {
                $query->where('active', true)->orWhereIn('id', $user->accessProfiles()->select('access_profiles.id'));
            })],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'supervisor_ids' => ['nullable', 'array', 'max:2'],
            'supervisor_ids.*' => ['nullable', 'integer', 'exists:users,id', 'different:'.$user->id],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $supervisorIds = collect($validated['supervisor_ids'] ?? [])->unique()->values();
        if ($user->is(auth()->user()) && ($validated['role'] !== $user->role || array_key_exists('job_position_id', $validated) || array_key_exists('profile_ids', $validated) || ! empty($validated['clear_profiles']))) {
            throw ValidationException::withMessages(['profile_ids' => 'Você não pode alterar seus próprios acessos ou cargo.']);
        }
        if ($user->role === 'admin' && $validated['role'] !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            throw ValidationException::withMessages(['role' => 'O último administrador não pode perder o acesso administrativo.']);
        }
        $this->validateSupervisors($validated['role'], $supervisorIds, $user->id);
        DB::transaction(function () use ($validated, $user, $supervisorIds): void {
            $user->update(collect($validated)->except('supervisor_ids', 'profile_ids', 'clear_profiles')->all());
            $this->syncSupervisors($user, $supervisorIds);
            if (! empty($validated['clear_profiles'])) {
                $user->accessProfiles()->detach();
                Log::notice('Access profiles removed from user', ['actor_id' => auth()->id(), 'user_id' => $user->id]);
            } elseif (! empty($validated['profile_ids'])) {
                $user->accessProfiles()->sync($validated['profile_ids'] ?? []);
                Log::notice('Access profiles updated for user', ['actor_id' => auth()->id(), 'user_id' => $user->id, 'profile_ids' => $validated['profile_ids']]);
            }
        });

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
            'jobPositions' => JobPosition::where('active', true)->orWhere('id', User::find($exceptUserId)?->job_position_id)->orderBy('name')->get(),
            'accessProfiles' => AccessProfile::where('active', true)
                ->when($exceptUserId, fn ($query) => $query->orWhereIn('id', User::find($exceptUserId)?->accessProfiles()->select('access_profiles.id') ?? []))
                ->orderBy('name')->get(),
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
