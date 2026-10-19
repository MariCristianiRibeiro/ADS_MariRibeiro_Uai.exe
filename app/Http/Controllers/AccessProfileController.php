<?php

namespace App\Http\Controllers;

use App\Models\AccessProfile;
use App\Models\Permission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccessProfileController extends Controller
{
    public function index(Request $request): View
    {
        $profiles = AccessProfile::withCount('permissions')->when($request->filled('search'), fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower(trim($request->search)).'%']))
            ->orderBy('name')->paginate(15)->withQueryString();

        return view('access-profiles.index', compact('profiles'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.create'), 403);

        return view('access-profiles.form', ['profile' => new AccessProfile, 'permissions' => Permission::orderBy('resource')->orderBy('action')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('access_profiles.create'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:access_profiles,name'], 'description' => ['nullable', 'string'], 'active' => ['sometimes', 'boolean'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'distinct', 'exists:permissions,id']]);
        $this->ensureCanGrantPermissions($request, $validated['permissions'] ?? []);
        DB::transaction(function () use ($validated): void {
            $profile = AccessProfile::create([...collect($validated)->except('permissions')->all(), 'active' => $validated['active'] ?? true]);
            $profile->permissions()->sync($validated['permissions'] ?? []);
        });
        Log::info('Access profile created', ['actor_id' => $request->user()->id, 'profile_name' => $validated['name'], 'permission_count' => count($validated['permissions'] ?? [])]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil criado com sucesso.');
    }

    public function edit(AccessProfile $accessProfile): View
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.edit'), 403);

        return view('access-profiles.form', ['profile' => $accessProfile->load('permissions'), 'permissions' => Permission::orderBy('resource')->orderBy('action')->get()]);
    }

    public function update(Request $request, AccessProfile $accessProfile): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('access_profiles.edit'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('access_profiles', 'name')->ignore($accessProfile->id)], 'description' => ['nullable', 'string'], 'active' => ['sometimes', 'boolean'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'distinct', 'exists:permissions,id']]);
        $this->ensureCanGrantPermissions($request, $validated['permissions'] ?? []);
        DB::transaction(function () use ($validated, $accessProfile): void {
            $accessProfile->update([...collect($validated)->except('permissions')->all(), 'active' => $validated['active'] ?? false]);
            $accessProfile->permissions()->sync($validated['permissions'] ?? []);
        });
        Log::info('Access profile updated', ['actor_id' => $request->user()->id, 'profile_id' => $accessProfile->id, 'permission_count' => count($validated['permissions'] ?? [])]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil atualizado com sucesso.');
    }

    public function deactivate(Request $request, AccessProfile $accessProfile): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.administer'), 403);

        $accessProfile->update(['active' => false]);
        Log::notice('Access profile deactivated', ['actor_id' => $request->user()->id, 'profile_id' => $accessProfile->id]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil inativado com sucesso.');
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    private function ensureCanGrantPermissions(Request $request, array $permissionIds): void
    {
        $user = $request->user();

        if ($user->isAdmin() || $user->role === 'hr') {
            return;
        }

        $authorizedPermissionIds = Permission::query()->whereIn('id', $permissionIds)->get()
            ->filter(fn (Permission $permission) => $user->hasPermission($permission->name))
            ->modelKeys();

        if (count($authorizedPermissionIds) !== count(array_unique($permissionIds))) {
            throw ValidationException::withMessages(['permissions' => 'Você não pode conceder permissões que não possui autorização para administrar.']);
        }
    }
}
