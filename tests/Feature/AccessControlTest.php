<?php

use App\Models\AccessProfile;
use App\Models\JobPosition;
use App\Models\Meeting;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('restricts profile administration and persists profile permissions', function () {
    $regularUser = User::factory()->create(['role' => 'participant']);
    $this->actingAs($regularUser)->get(route('access-profiles.index'))->assertForbidden();

    $administrator = createAdminUser();
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
    $administrator = createAdminUser();
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
    $administrator = createAdminUser();
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
    $administrator = createAdminUser();
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
    $administrator = createAdminUser();

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
    $hrUser = createUserWithPermissions(['users.administer'], ['role' => 'hr']);

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
    $reportPermission = Permission::create(['name' => 'finance.export', 'resource' => 'finance', 'action' => 'export', 'description' => 'Exportar financeiro']);
    $profile->permissions()->attach($manageProfiles);
    $user->accessProfiles()->attach($profile);

    $this->actingAs($user)->post(route('access-profiles.store'), [
        'name' => 'Escalada',
        'permissions' => [$reportPermission->id],
    ])->assertSessionHasErrors('permissions');
});

it('assigns a selected profile through the administrative participant form', function () {
    $administrator = createAdminUser();
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

it('does not grant system permissions from the organizational role alone', function () {
    $administratorRole = User::factory()->create(['role' => 'admin']);

    $this->actingAs($administratorRole)->get(route('users.index'))->assertForbidden();
    expect($administratorRole->hasPermission('users.administer'))->toBeFalse();
});

it('composes permissions from multiple profiles regardless of the user role', function () {
    $user = User::factory()->create(['role' => 'manager']);

    foreach (['teams.view', 'reports.costs'] as $permissionName) {
        [$resource, $action] = explode('.', $permissionName, 2);
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['resource' => $resource, 'action' => $action, 'description' => $permissionName],
        );
        $profile = AccessProfile::create(['name' => 'Acesso '.$permissionName, 'active' => true]);
        $profile->permissions()->attach($permission);
        $user->accessProfiles()->attach($profile);
    }

    expect($user->hasPermission('teams.view'))->toBeTrue()
        ->and($user->hasPermission('reports.costs'))->toBeTrue()
        ->and($user->hasPermission('users.administer'))->toBeFalse();
});

it('allows authorized administrators to register new permission definitions', function () {
    $administrator = createAdminUser();

    $this->actingAs($administrator)->post(route('access-profiles.permissions.store'), [
        'resource' => 'finance',
        'action' => 'view',
        'description' => 'Visualizar financeiro',
    ])->assertRedirect(route('access-profiles.index'));

    $this->assertDatabaseHas('permissions', [
        'name' => 'finance.view',
        'resource' => 'finance',
        'action' => 'view',
        'description' => 'Visualizar financeiro',
    ]);
});

it('allows delegated permission administrators to grant permissions they do not hold', function () {
    $delegate = createUserWithPermissions(
        ['access_profiles.create', 'access_profiles.administer'],
        ['role' => 'director_network'],
    );
    $costPermission = Permission::firstOrCreate(
        ['name' => 'reports.costs'],
        ['resource' => 'reports', 'action' => 'costs', 'description' => 'Visualizar custos reais'],
    );

    $this->actingAs($delegate)->post(route('access-profiles.store'), [
        'name' => 'Acesso financeiro delegado',
        'permissions' => [$costPermission->id],
    ])->assertRedirect(route('access-profiles.index'));

    $profile = AccessProfile::where('name', 'Acesso financeiro delegado')->firstOrFail();
    expect($profile->permissions()->whereKey($costPermission->id)->exists())->toBeTrue();
});

it('does not let user managers assign profiles with permissions they cannot grant', function () {
    $userManager = createUserWithPermissions(['users.administer'], ['role' => 'hr']);
    $target = User::factory()->create(['role' => 'participant']);
    $profile = AccessProfile::create(['name' => 'Custos reais', 'active' => true]);
    $permission = Permission::firstOrCreate(
        ['name' => 'reports.costs'],
        ['resource' => 'reports', 'action' => 'costs', 'description' => 'Visualizar custos reais'],
    );
    $profile->permissions()->attach($permission);

    $this->actingAs($userManager)->put(route('users.update', $target), [
        'name' => $target->name,
        'email' => $target->email,
        'status' => 'active',
        'role' => 'participant',
        'profile_ids' => [$profile->id],
        'supervisor_ids' => [],
    ])->assertSessionHasErrors('profile_ids');

    expect($target->fresh()->accessProfiles()->whereKey($profile->id)->exists())->toBeFalse();
});

it('keeps meeting cost data out of pages without the costs permission', function () {
    $jobPosition = JobPosition::create(['name' => 'Financeiro', 'code' => 'FIN', 'hourly_rate' => 247, 'active' => true]);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create'], ['role' => 'manager']);
    $attendee = User::factory()->create(['job_position_id' => $jobPosition->id]);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião de orçamento',
        'date' => now()->toDateString(),
        'status' => 'scheduled',
    ]);
    $meeting->users()->attach($attendee, ['attendance_status' => 'confirmed']);

    $this->actingAs($owner)->get(route('meetings.create'))
        ->assertOk()
        ->assertDontSee('hourlyRate', false)
        ->assertDontSee('Valor-hora dos participantes')
        ->assertDontSee('Custo previsto');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('hourlyRate', false)
        ->assertDontSee('Investimento acumulado');

    $costViewer = createUserWithPermissions(
        ['meetings.view', 'meetings.create', 'reports.costs'],
        ['role' => 'manager'],
    );

    $this->actingAs($costViewer)->get(route('meetings.create'))
        ->assertOk()
        ->assertSee('hourlyRate', false)
        ->assertSee('Custo estimado por participante');
});

it('records profile assignments and permission changes in the audit history', function () {
    $administrator = createAdminUser();
    $profile = AccessProfile::create(['name' => 'Relatórios', 'active' => true]);
    $permission = Permission::firstOrCreate(
        ['name' => 'reports.view'],
        ['resource' => 'reports', 'action' => 'view', 'description' => 'Visualizar relatórios'],
    );

    $this->actingAs($administrator)->put(route('access-profiles.update', $profile), [
        'name' => $profile->name,
        'active' => '1',
        'permissions' => [$permission->id],
    ])->assertRedirect(route('access-profiles.index'));

    $this->post(route('users.store'), [
        'name' => 'Pessoa com perfil',
        'email' => 'perfil@example.com',
        'password' => 'Senha@123',
        'status' => 'active',
        'role' => 'participant',
        'profile_ids' => [$profile->id],
        'supervisor_ids' => [],
    ])->assertRedirect(route('users.index'));

    $assignedUser = User::where('email', 'perfil@example.com')->firstOrFail();

    $this->put(route('access-profiles.update', $profile), [
        'name' => $profile->name,
        'active' => '1',
        'permissions' => [],
    ])->assertRedirect(route('access-profiles.index'));

    $this->put(route('users.update', $assignedUser), [
        'name' => $assignedUser->name,
        'email' => $assignedUser->email,
        'status' => 'active',
        'role' => 'participant',
        'clear_profiles' => '1',
        'supervisor_ids' => [],
    ])->assertRedirect(route('users.index'));

    $this->assertDatabaseHas('access_control_audits', [
        'actor_id' => $administrator->id,
        'subject_user_id' => $assignedUser->id,
        'access_profile_id' => $profile->id,
        'event' => 'profile_assigned',
    ]);
    $this->assertDatabaseHas('access_control_audits', [
        'actor_id' => $administrator->id,
        'subject_user_id' => $assignedUser->id,
        'access_profile_id' => $profile->id,
        'event' => 'profile_removed',
    ]);
    $this->assertDatabaseHas('access_control_audits', [
        'actor_id' => $administrator->id,
        'access_profile_id' => $profile->id,
        'permission_id' => $permission->id,
        'event' => 'permission_granted',
    ]);
    $this->assertDatabaseHas('access_control_audits', [
        'actor_id' => $administrator->id,
        'access_profile_id' => $profile->id,
        'permission_id' => $permission->id,
        'event' => 'permission_revoked',
    ]);

    $this->get(route('access-profiles.audit'))->assertOk()->assertSee('Histórico de acessos');
});
