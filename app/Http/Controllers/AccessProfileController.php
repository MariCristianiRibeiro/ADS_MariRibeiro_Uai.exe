<?php

namespace App\Http\Controllers;

use App\Models\AccessProfile;
use App\Models\Permission;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
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

    public function store(Request $request, AccessControlService $accessControl): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('access_profiles.create'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', 'unique:access_profiles,name'], 'description' => ['nullable', 'string'], 'active' => ['sometimes', 'boolean'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'distinct', 'exists:permissions,id']]);
        DB::transaction(function () use ($validated, $request, $accessControl): void {
            $profile = AccessProfile::create([...collect($validated)->except('permissions')->all(), 'active' => $validated['active'] ?? true]);
            $accessControl->syncProfilePermissions($request->user(), $profile, $validated['permissions'] ?? []);
            $accessControl->record($request->user(), 'profile_created', null, $profile, null, [
                'profile_name' => $profile->name,
            ]);
        });
        Log::info('Access profile created', ['actor_id' => $request->user()->id, 'profile_name' => $validated['name'], 'permission_count' => count($validated['permissions'] ?? [])]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil criado com sucesso.');
    }

    public function edit(AccessProfile $accessProfile): View
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.edit'), 403);

        return view('access-profiles.form', ['profile' => $accessProfile->load('permissions'), 'permissions' => Permission::orderBy('resource')->orderBy('action')->get()]);
    }

    public function update(Request $request, AccessProfile $accessProfile, AccessControlService $accessControl): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('access_profiles.edit'), 403);
        $validated = $request->validate(['name' => ['required', 'string', 'max:255', Rule::unique('access_profiles', 'name')->ignore($accessProfile->id)], 'description' => ['nullable', 'string'], 'active' => ['sometimes', 'boolean'], 'permissions' => ['array'], 'permissions.*' => ['integer', 'distinct', 'exists:permissions,id']]);
        $previousName = $accessProfile->name;
        DB::transaction(function () use ($validated, $accessProfile, $request, $accessControl, $previousName): void {
            $accessProfile->update([...collect($validated)->except('permissions')->all(), 'active' => $validated['active'] ?? false]);
            $accessControl->syncProfilePermissions($request->user(), $accessProfile, $validated['permissions'] ?? []);
            $accessControl->record($request->user(), 'profile_updated', null, $accessProfile, null, [
                'previous_name' => $previousName,
                'profile_name' => $accessProfile->name,
                'active' => $accessProfile->active,
            ]);
        });
        Log::info('Access profile updated', ['actor_id' => $request->user()->id, 'profile_id' => $accessProfile->id, 'permission_count' => count($validated['permissions'] ?? [])]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil atualizado com sucesso.');
    }

    public function deactivate(Request $request, AccessProfile $accessProfile, AccessControlService $accessControl): RedirectResponse
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.administer'), 403);

        DB::transaction(function () use ($accessProfile, $accessControl, $request): void {
            $accessProfile->update(['active' => false]);
            $accessControl->record($request->user(), 'profile_deactivated', null, $accessProfile, null, [
                'profile_name' => $accessProfile->name,
            ]);
        });
        Log::notice('Access profile deactivated', ['actor_id' => $request->user()->id, 'profile_id' => $accessProfile->id]);

        return redirect()->route('access-profiles.index')->with('success', 'Perfil inativado com sucesso.');
    }
}
