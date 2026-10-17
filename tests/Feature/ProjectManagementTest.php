<?php

use App\Models\Meeting;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a project using only its name and generates its internal code', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $this->actingAs($manager)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertSee('Nome do projeto')
        ->assertDontSee('Código do projeto');

    $this->actingAs($manager)
        ->post(route('projects.store'), ['name' => 'Portal de Atendimento'])
        ->assertRedirect(route('projects.index'));

    $this->assertDatabaseHas('projects', [
        'name' => 'Portal de Atendimento',
        'code' => 'PORTAL-DE-ATENDIMENTO',
        'manager_id' => $manager->id,
        'status' => 'active',
    ]);

    $this->actingAs($manager)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Portal de Atendimento');
});

it('returns to meeting creation with the new project selected', function () {
    $manager = User::factory()->create(['role' => 'manager']);

    $response = $this->actingAs($manager)
        ->post(route('projects.store'), [
            'name' => 'Projeto de Integração',
            'return_to' => 'meeting',
        ]);

    $project = Project::where('name', 'Projeto de Integração')->firstOrFail();

    $response->assertRedirect(route('meetings.create', ['project_id' => $project->id]));

    $this->actingAs($manager)
        ->get(route('meetings.create', ['project_id' => $project->id]))
        ->assertOk()
        ->assertSee('Projeto de Integração')
        ->assertSee('value="'.$project->id.'" selected', false);
});

it('rejects a project without a name', function () {
    $this->actingAs(User::factory()->create())
        ->from(route('projects.create'))
        ->post(route('projects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('shows completed meeting time in the project report for its manager', function () {
    $manager = User::factory()->create(['role' => 'manager']);
    $project = Project::create([
        'manager_id' => $manager->id,
        'name' => 'Projeto sem equipe',
        'code' => 'PROJETO-SEM-EQUIPE',
    ]);
    Meeting::create([
        'user_id' => $manager->id,
        'project_id' => $project->id,
        'title' => 'Reunião do projeto',
        'date' => now()->toDateString(),
        'duration_minutes' => 60,
        'actual_duration_seconds' => 1830,
        'status' => 'completed',
    ]);

    Meeting::create([
        'user_id' => $manager->id,
        'project_id' => $project->id,
        'title' => 'Reunião adicional do projeto',
        'date' => now()->toDateString(),
        'duration_minutes' => 60,
        'actual_duration_seconds' => 270,
        'status' => 'completed',
    ]);

    $this->actingAs($manager)
        ->getJson(route('reports.meetings'))
        ->assertOk()
        ->assertJsonPath('rows.0.project', 'Projeto sem equipe')
        ->assertJsonPath('rows.0.meetings', 2)
        ->assertJsonPath('rows.0.actual_seconds', 2100)
        ->assertJsonPath('rows.0.actual_hours', 0.58);
});
