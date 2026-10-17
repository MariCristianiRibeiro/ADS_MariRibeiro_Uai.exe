<?php

use App\Models\Directorate;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a team under a network and generates its internal code', function () {
    $hr = User::factory()->create(['role' => 'hr']);

    $this->actingAs($hr)
        ->get(route('teams.create'))
        ->assertOk()
        ->assertSee('Nome da equipe')
        ->assertSee('Rede');

    $this->actingAs($hr)
        ->post(route('teams.store'), [
            'name' => 'Equipe Comercial',
            'network' => 'Rede Sul',
        ])
        ->assertRedirect(route('teams.index'));

    $directorate = Directorate::where('name', 'Rede Sul')->firstOrFail();

    $this->assertDatabaseHas('teams', [
        'directorate_id' => $directorate->id,
        'name' => 'Equipe Comercial',
        'code' => 'EQUIPE-COMERCIAL',
        'active' => true,
    ]);

    $this->actingAs($hr)
        ->get(route('teams.index'))
        ->assertOk()
        ->assertSee('Equipe Comercial')
        ->assertSee('Rede Sul');
});

it('reuses a network and gives teams unique generated codes within it', function () {
    $directorate = Directorate::create([
        'name' => 'Rede Norte',
        'code' => 'REDE-NORTE',
    ]);
    $hr = User::factory()->create(['role' => 'hr']);

    $this->actingAs($hr)->post(route('teams.store'), [
        'name' => 'Operações',
        'network' => 'rede norte',
    ])->assertRedirect(route('teams.index'));

    $this->actingAs($hr)->post(route('teams.store'), [
        'name' => 'Operações',
        'network' => 'Rede Norte',
    ])->assertRedirect(route('teams.index'));

    expect(Directorate::where('name', 'Rede Norte')->count())->toBe(1);

    $this->assertDatabaseHas('teams', [
        'directorate_id' => $directorate->id,
        'name' => 'Operações',
        'code' => 'OPERACOES',
    ]);
    $this->assertDatabaseHas('teams', [
        'directorate_id' => $directorate->id,
        'name' => 'Operações',
        'code' => 'OPERACOES-2',
    ]);
});

it('forbids participants from managing teams', function () {
    $this->actingAs(User::factory()->create(['role' => 'participant']))
        ->get(route('teams.index'))
        ->assertForbidden();
});

it('requires both a team name and network', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->from(route('teams.create'))
        ->post(route('teams.store'), [
            'name' => '',
            'network' => '',
        ])
        ->assertSessionHasErrors(['name', 'network']);

    expect(Team::count())->toBe(0);
});
