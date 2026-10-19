<?php

use App\Models\AccessProfile;
use App\Models\JobPosition;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts profile administration and persists profile permissions', function () {
    $regularUser = User::factory()->create(['role' => 'participant']);
    $this->actingAs($regularUser)->get(route('access-profiles.index'))->assertForbidden();

    $administrator = User::factory()->create(['role' => 'admin']);
    $permission = Permission::firstOrCreate(['name' => 'participants.view'], ['resource' => 'participants', 'action' => 'view', 'description' => 'Visualizar participantes']);
    $this->actingAs($administrator)->post(route('access-profiles.store'), [
        'name' => 'Leitor',
        'description' => 'Acesso somente para leitura',
        'permissions' => [$permission->id],
    ])->assertRedirect(route('access-profiles.index'));

    $profile = AccessProfile::where('name', 'Leitor')->firstOrFail();
    expect($profile->permissions()->whereKey($permission->id)->exists())->toBeTrue();
});

it('assigns a cargo and access profile to a managed user', function () {
    $administrator = User::factory()->create(['role' => 'admin']);
    $user = User::factory()->create(['role' => 'participant']);
    $jobPosition = JobPosition::create(['name' => 'Analista', 'code' => 'ANL', 'hourly_rate' => 75, 'active' => true]);
    $profile = AccessProfile::create(['name' => 'Editor', 'active' => true]);

    $this->actingAs($administrator)->put(route('users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'status' => 'active',
        'role' => 'participant',
        'job_position_id' => $jobPosition->id,
        'profile_ids' => [$profile->id],
        'supervisor_ids' => [],
    ])->assertRedirect(route('users.index'));

    expect($user->fresh()->job_position_id)->toBe($jobPosition->id)
        ->and($user->fresh()->accessProfiles()->whereKey($profile->id)->exists())->toBeTrue();
});

it('prevents users from changing their own access assignments', function () {
    $administrator = User::factory()->create(['role' => 'admin']);
    $profile = AccessProfile::create(['name' => 'Privilegiado', 'active' => true]);

    $this->actingAs($administrator)->put(route('users.update', $administrator), [
        'name' => $administrator->name,
        'email' => $administrator->email,
        'status' => 'active',
        'role' => 'admin',
        'profile_ids' => [$profile->id],
        'supervisor_ids' => [],
    ])->assertSessionHasErrors('profile_ids');

    expect($administrator->fresh()->accessProfiles()->whereKey($profile->id)->exists())->toBeFalse();
});

it('updates and deactivates profiles and cargos without deleting assigned records', function () {
    $administrator = User::factory()->create(['role' => 'admin']);
    $profile = AccessProfile::create(['name' => 'Rascunho', 'active' => true]);
    $permission = Permission::firstOrCreate(['name' => 'reports.view'], ['resource' => 'reports', 'action' => 'view', 'description' => 'Visualizar relatórios']);

    $this->actingAs($administrator)->put(route('access-profiles.update', $profile), [
        'name' => 'Leitor de relatórios',
        'active' => '1',
        'permissions' => [$permission->id],
    ])->assertRedirect(route('access-profiles.index'));
    expect($profile->fresh()->permissions()->whereKey($permission->id)->exists())->toBeTrue();
    $this->patch(route('access-profiles.deactivate', $profile))->assertRedirect(route('access-profiles.index'));
    expect($profile->fresh()->active)->toBeFalse();

    $position = JobPosition::create(['name' => 'Coordenador', 'code' => 'COORD', 'hourly_rate' => 100, 'active' => true]);
    $this->put(route('job-positions.update', $position), [
        'name' => 'Coordenador regional',
        'code' => 'COORD-REG',
        'hourly_rate' => 120,
        'active' => '1',
    ])->assertRedirect(route('job-positions.index'));
    $this->patch(route('job-positions.deactivate', $position))->assertRedirect(route('job-positions.index'));

    expect($position->fresh()->active)->toBeFalse()
        ->and($position->fresh()->name)->toBe('Coordenador regional');
});

it('generates a unique internal code for each cargo without user input', function () {
    $administrator = User::factory()->create(['role' => 'admin']);

    $this->actingAs($administrator)->post(route('job-positions.store'), [
        'name' => 'Analista de Sistemas',
        'hourly_rate' => 80,
    ])->assertRedirect(route('job-positions.index'));

    $this->post(route('job-positions.store'), [
        'name' => 'analista de sistemas',
        'hourly_rate' => 90,
    ])->assertRedirect(route('job-positions.index'));

    expect(JobPosition::where('name', 'Analista de Sistemas')->value('code'))->toBe('ANALISTA-DE-SISTEMAS')
        ->and(JobPosition::where('name', 'analista de sistemas')->value('code'))->toBe('ANALISTA-DE-SISTEMAS-2');
});

it('blocks a staff user from elevating their own legacy role', function () {
    $hrUser = User::factory()->create(['role' => 'hr']);

    $this->actingAs($hrUser)->put(route('users.update', $hrUser), [
        'name' => $hrUser->name,
        'email' => $hrUser->email,
        'status' => 'active',
        'role' => 'admin',
        'supervisor_ids' => [],
    ])->assertSessionHasErrors('profile_ids');

    expect($hrUser->fresh()->role)->toBe('hr');
});

it('uses assigned permissions in server-side authorization checks', function () {
    $user = User::factory()->create(['role' => 'participant']);
    $profile = AccessProfile::create(['name' => 'Consulta de cargos', 'active' => true]);
    $permission = Permission::firstOrCreate(['name' => 'job_positions.view'], ['resource' => 'job_positions', 'action' => 'view', 'description' => 'Visualizar cargos']);
    $profile->permissions()->attach($permission);
    $user->accessProfiles()->attach($profile);

    $this->actingAs($user)->get(route('job-positions.index'))->assertOk();
    $position = JobPosition::create(['name' => 'Analista', 'code' => 'ANALISTA', 'hourly_rate' => 80, 'active' => true]);
    $this->get(route('job-positions.edit', $position))->assertForbidden();
});

it('does not let profile editors grant permissions they do not hold', function () {
    $user = User::factory()->create(['role' => 'participant']);
    $profile = AccessProfile::create(['name' => 'Editor de perfis', 'active' => true]);
    $manageProfiles = Permission::firstOrCreate(['name' => 'access_profiles.create'], ['resource' => 'access_profiles', 'action' => 'create', 'description' => 'Criar perfis']);
    $reportPermission = Permission::create(['name' => 'reports.export', 'resource' => 'reports', 'action' => 'export', 'description' => 'Exportar relatórios']);
    $profile->permissions()->attach($manageProfiles);
    $user->accessProfiles()->attach($profile);

    $this->actingAs($user)->post(route('access-profiles.store'), [
        'name' => 'Escalada',
        'permissions' => [$reportPermission->id],
    ])->assertSessionHasErrors('permissions');
});

it('assigns a selected profile through the administrative participant form', function () {
    $administrator = User::factory()->create(['role' => 'admin']);
    $profile = AccessProfile::create(['name' => 'Equipe operacional', 'active' => true]);

    $this->actingAs($administrator)->post(route('participants.store'), [
        'name' => 'Pessoa cadastrada',
        'email' => 'pessoa@example.com',
        'password' => 'Senha@123',
        'status' => 'active',
        'type' => 'internal',
        'profile_ids' => [$profile->id],
    ])->assertRedirect(route('participants.index'));

    $member = User::where('email', 'pessoa@example.com')->firstOrFail();
    expect($member->accessProfiles()->whereKey($profile->id)->exists())->toBeTrue();
});
