<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function create(): View
    {
        abort_unless(auth()->user()->hasPermission('access_profiles.administer'), 403);

        return view('access-profiles.permissions.create');
    }

    public function store(Request $request, AccessControlService $accessControl): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('access_profiles.administer'), 403);

        $validated = $request->validate([
            'resource' => ['required', 'string', 'max:100', 'regex:/\A[a-z][a-z0-9_]*\z/'],
            'action' => [
                'required',
                'string',
                'max:100',
                'regex:/\A[a-z][a-z0-9_]*\z/',
                Rule::unique('permissions', 'action')->where(fn ($query) => $query->where('resource', $request->input('resource'))),
            ],
            'description' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($validated, $request, $accessControl): void {
            $permission = Permission::create([
                ...$validated,
                'name' => $validated['resource'].'.'.$validated['action'],
            ]);

            $accessControl->record($request->user(), 'permission_created', null, null, $permission, [
                'permission_name' => $permission->name,
            ]);
        });

        return redirect()->route('access-profiles.index')->with('success', 'Permissão cadastrada com sucesso.');
    }
}
