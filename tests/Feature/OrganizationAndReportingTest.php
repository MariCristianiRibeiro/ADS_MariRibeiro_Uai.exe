<?php

use App\Models\Directorate;
use App\Models\JobPosition;
use App\Models\Meeting;
use App\Models\Participant;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates real project cost for a manager without exposing individual rates', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $member = User::factory()->create(['role' => 'participant', 'salary' => 44000]);
    $directorate = Directorate::create(['name' => 'Operações', 'code' => 'OPS']);
    $team = Team::create(['directorate_id' => $directorate->id, 'manager_id' => $manager->id, 'name' => 'Produto', 'code' => 'PROD']);
    $position = JobPosition::create(['name' => 'Analista', 'code' => 'ANL', 'hourly_rate' => 100]);
    $member->update(['team_id' => $team->id, 'job_position_id' => $position->id]);
    $member->forceFill(['salary' => 44000])->save();
    $project = Project::create(['team_id' => $team->id, 'name' => 'Portal', 'code' => 'PORTAL']);
    $participant = Participant::create(['user_id' => $manager->id, 'member_user_id' => $member->id, 'name' => $member->name, 'email' => $member->email, 'status' => 'active', 'salary' => 44000]);
    $participant->forceFill(['salary' => 44000])->save();
    $meeting = Meeting::create(['user_id' => $manager->id, 'project_id' => $project->id, 'title' => 'Planejamento', 'date' => now()->toDateString(), 'duration_minutes' => 120, 'actual_duration_seconds' => 1800, 'status' => 'completed']);
    $meeting->participants()->attach($participant, ['attendance_status' => 'confirmed']);

    $response = $this->actingAs($manager)->getJson('/reports/meetings');

    $response->assertOk()
        ->assertJsonPath('visibility', 'real_cost')
        ->assertJsonPath('rows.0.planned_hours', 2)
        ->assertJsonPath('rows.0.actual_hours', 0.5)
        ->assertJsonPath('rows.0.actual_seconds', 1800)
        ->assertJsonPath('rows.0.estimated_cost', 200)
        ->assertJsonPath('rows.0.real_cost', 50);
});

it('does not expose real cost to a collaborator', function () {
    $user = User::factory()->create(['role' => 'participant']);

    $this->actingAs($user)->getJson('/reports/meetings')
        ->assertOk()
        ->assertJsonPath('visibility', 'hours')
        ->assertJsonMissingPath('real_cost');
});

it('reports planned hours separately from confirmed actual attendance hours to collaborators', function () {
    $owner = User::factory()->create(['role' => 'manager']);
    $member = User::factory()->create(['role' => 'participant']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Acompanhamento',
        'date' => now()->toDateString(),
        'duration_minutes' => 90,
        'actual_duration_seconds' => 1800,
        'status' => 'completed',
    ]);
    $meeting->users()->attach($member, ['attendance_status' => 'confirmed']);

    $this->actingAs($member)->getJson('/reports/meetings')
        ->assertOk()
        ->assertJsonPath('hours', 1.5)
        ->assertJsonPath('planned_hours', 1.5)
        ->assertJsonPath('actual_hours', 0.5)
        ->assertJsonPath('actual_seconds', 1800)
        ->assertJsonMissingPath('real_cost');
});

it('allows only admin and hr to manage the people directory', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/users')
        ->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'hr']))
        ->get('/users')
        ->assertOk();

    $this->actingAs(User::factory()->create(['role' => 'participant']))
        ->get('/users')
        ->assertForbidden();
});

it('shows up to two supervisors beside the profile in the people directory', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $manager = User::factory()->create(['name' => 'Carlos Gerente', 'role' => 'manager']);
    $director = User::factory()->create(['name' => 'Ana Diretora', 'role' => 'director_network']);
    $participant = User::factory()->create(['role' => 'participant']);
    $participant->supervisors()->attach([
        $manager->id => ['relationship_type' => 'manager_collaborator'],
        $director->id => ['relationship_type' => 'network_director_collaborator'],
    ]);

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk()
        ->assertSeeInOrder(['Perfil', 'Superior responsável 1', 'Superior responsável 2'])
        ->assertSee('Carlos Gerente')
        ->assertSee('Ana Diretora');
});

it('creates a participant profile for every managed user except admins', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/users', [
        'name' => 'Maria RH',
        'email' => 'maria.rh@example.com',
        'password' => 'Senha@123',
        'status' => 'active',
        'role' => 'hr',
    ])->assertRedirect('/users');

    $hr = User::where('email', 'maria.rh@example.com')->firstOrFail();
    $this->assertDatabaseHas('participants', [
        'user_id' => $hr->id,
        'member_user_id' => $hr->id,
        'email' => $hr->email,
        'role' => 'hr',
    ]);

    $this->actingAs($admin)->post('/users', [
        'name' => 'Administrador Geral',
        'email' => 'admin.novo@example.com',
        'password' => 'Senha@123',
        'status' => 'active',
        'role' => 'admin',
    ])->assertRedirect('/users');

    $this->assertDatabaseMissing('participants', ['email' => 'admin.novo@example.com']);
});

it('supports optional and multiple hierarchical supervisors', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $directorA = User::factory()->create(['role' => 'director_network']);
    $directorB = User::factory()->create(['role' => 'director_network']);
    $manager = User::factory()->create(['role' => 'manager']);
    $collaborator = User::factory()->create(['role' => 'participant']);

    $this->actingAs($admin)->put('/users/'.$manager->id, [
        'name' => $manager->name,
        'email' => $manager->email,
        'status' => 'active',
        'role' => 'manager',
        'supervisor_ids' => [$directorA->id],
    ])->assertRedirect('/users');

    $this->actingAs($admin)->put('/users/'.$collaborator->id, [
        'name' => $collaborator->name,
        'email' => $collaborator->email,
        'status' => 'active',
        'role' => 'participant',
        'supervisor_ids' => [$directorA->id, $directorB->id],
    ])->assertRedirect('/users');

    expect($manager->fresh()->supervisors->modelKeys())->toBe([$directorA->id])
        ->and($collaborator->fresh()->supervisors->modelKeys())->toEqualCanonicalizing([$directorA->id, $directorB->id]);
});

it('allows a director to report to an executive and another director', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $executive = User::factory()->create(['role' => 'executive']);
    $seniorDirector = User::factory()->create(['role' => 'director']);
    $director = User::factory()->create(['role' => 'director']);

    $this->actingAs($admin)->put('/users/'.$director->id, [
        'name' => $director->name,
        'email' => $director->email,
        'status' => 'active',
        'role' => 'director',
        'supervisor_ids' => [$seniorDirector->id, $executive->id],
    ])->assertRedirect('/users');

    $links = $director->fresh()->supervisors->keyBy('id');

    expect($links->keys()->all())->toEqualCanonicalizing([$seniorDirector->id, $executive->id])
        ->and($links[$seniorDirector->id]->pivot->relationship_type)->toBe('director_director')
        ->and($links[$executive->id]->pivot->relationship_type)->toBe('executive_director');
});

it('creates a director with an executive and a senior director', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $executive = User::factory()->create(['role' => 'executive']);
    $seniorDirector = User::factory()->create(['role' => 'director']);

    $response = $this->actingAs($admin)->post('/users', [
        'name' => 'Diretora de Operações',
        'email' => 'diretora@empresa.test',
        'password' => 'Senha@123',
        'status' => 'active',
        'role' => 'director',
        'supervisor_ids' => [$executive->id, $seniorDirector->id],
    ]);

    $response->assertRedirect('/users');
    $director = User::where('email', 'diretora@empresa.test')->firstOrFail();
    expect($director->supervisors->modelKeys())->toEqualCanonicalizing([$executive->id, $seniorDirector->id]);
});

it('creates an executive without a supervisor', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->post('/users', [
        'name' => 'Executiva Financeira',
        'email' => 'executiva.financeira@example.test',
        'password' => 'Senha@123',
        'status' => 'active',
        'role' => 'executive',
    ])->assertRedirect('/users');

    $executive = User::where('email', 'executiva.financeira@example.test')->firstOrFail();
    expect($executive->role)->toBe('executive')
        ->and($executive->supervisors)->toBeEmpty();
});

it('shows active registered users as dynamic supervisor options', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->create(['name' => 'Marcus', 'role' => 'executive', 'status' => 'active']);

    $this->actingAs($admin)->get('/users/create')
        ->assertOk()
        ->assertSee('Marcus');
});
