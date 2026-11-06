<?php

namespace App\Services;

use App\Models\AccessControlAudit;
use App\Models\AccessProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AccessControlService
{
    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function ensureCanGrantPermissions(User $actor, array $permissionIds): void
    {
        if ($actor->hasPermission('access_profiles.administer')) {
            return;
        }

        $permissions = Permission::query()->whereKey($permissionIds)->get();

        if ($permissions->contains(fn (Permission $permission) => ! $actor->hasPermission($permission->name))) {
            throw ValidationException::withMessages([
                'permissions' => 'Você não pode conceder permissões que não possui autorização para administrar.',
            ]);
        }
    }

    /**
     * @param  array<int, int|string>  $profileIds
     */
    public function ensureCanAssignProfiles(User $actor, array $profileIds): void
    {
        if ($actor->hasPermission('access_profiles.administer')) {
            return;
        }

        $permissions = Permission::query()
            ->whereHas('profiles', fn ($query) => $query
                ->whereIn('access_profiles.id', $profileIds)
                ->where('access_profiles.active', true))
            ->get();

        if ($permissions->contains(fn (Permission $permission) => ! $actor->hasPermission($permission->name))) {
            throw ValidationException::withMessages([
                'profile_ids' => 'Você não pode atribuir perfis com permissões que não possui autorização para administrar.',
            ]);
        }
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     */
    public function syncProfilePermissions(User $actor, AccessProfile $profile, array $permissionIds): void
    {
        $this->ensureCanGrantPermissions($actor, $permissionIds);

        $currentIds = $profile->permissions()->pluck('permissions.id')->map(fn ($id) => (int) $id);
        $newIds = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->values();
        $permissions = Permission::query()->whereKey($currentIds->merge($newIds))->get()->keyBy('id');

        $profile->permissions()->sync($newIds->all());

        $this->recordPermissionChanges($actor, $profile, $currentIds, $newIds, $permissions);
    }

    /**
     * @param  array<int, int|string>  $profileIds
     */
    public function syncUserProfiles(User $actor, User $subject, array $profileIds): void
    {
        $profileIds = collect($profileIds)->map(fn ($id) => (int) $id)->unique()->values();
        $this->ensureCanAssignProfiles($actor, $profileIds->all());

        $currentIds = $subject->accessProfiles()->pluck('access_profiles.id')->map(fn ($id) => (int) $id);
        $profiles = AccessProfile::query()->whereKey($currentIds->merge($profileIds))->get()->keyBy('id');

        $subject->accessProfiles()->sync($profileIds->all());

        foreach ($profileIds->diff($currentIds) as $profileId) {
            $profile = $profiles->get($profileId);
            $this->record($actor, 'profile_assigned', $subject, $profile, null, [
                'profile_name' => $profile?->name,
            ]);
        }

        foreach ($currentIds->diff($profileIds) as $profileId) {
            $profile = $profiles->get($profileId);
            $this->record($actor, 'profile_removed', $subject, $profile, null, [
                'profile_name' => $profile?->name,
            ]);
        }
    }

    /**
     * @param  Collection<int, int>  $currentIds
     * @param  Collection<int, int>  $newIds
     * @param  Collection<int, Permission>  $permissions
     */
    private function recordPermissionChanges(
        User $actor,
        AccessProfile $profile,
        Collection $currentIds,
        Collection $newIds,
        Collection $permissions,
    ): void {
        foreach ($newIds->diff($currentIds) as $permissionId) {
            $permission = $permissions->get($permissionId);
            $this->record($actor, 'permission_granted', null, $profile, $permission, [
                'permission_name' => $permission?->name,
            ]);
        }

        foreach ($currentIds->diff($newIds) as $permissionId) {
            $permission = $permissions->get($permissionId);
            $this->record($actor, 'permission_revoked', null, $profile, $permission, [
                'permission_name' => $permission?->name,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public function record(
        User $actor,
        string $event,
        ?User $subject = null,
        ?AccessProfile $profile = null,
        ?Permission $permission = null,
        array $details = [],
    ): void {
        AccessControlAudit::create([
            'actor_id' => $actor->id,
            'subject_user_id' => $subject?->id,
            'access_profile_id' => $profile?->id,
            'permission_id' => $permission?->id,
            'event' => $event,
            'details' => [
                ...$details,
                'actor' => ['id' => $actor->id, 'name' => $actor->name],
                'subject' => $subject ? ['id' => $subject->id, 'name' => $subject->name] : null,
            ],
            'created_at' => now(),
        ]);
    }
}
