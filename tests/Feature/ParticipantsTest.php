<?php

use App\Models\Directorate;
use App\Models\Participant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('user can view participant management page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/participants')
        ->assertOk();
});

it('user can create a participant', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/participants', [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'password' => 'Senha@123',
            'phone' => '11999999999',
            'status' => 'active',
            'hourly_rate' => '125.50',
        ])
        ->assertRedirect('/participants');

    $this->assertDatabaseHas('participants', [
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'status' => 'active',
    ]);

    $account = User::where('email', 'maria@example.com')->firstOrFail();
    $this->assertCredentials(['email' => 'maria@example.com', 'password' => 'Senha@123']);

    expect($account->role)->toBe('participant')
        ->and($account->status)->toBe('active')
        ->and(Participant::where('member_user_id', $account->id)->exists())->toBeTrue();

    $this->assertDatabaseHas('participants', [
        'user_id' => $user->id,
        'member_user_id' => $account->id,
        'email' => 'maria@example.com',
        'hourly_rate' => '125.50',
    ]);

    $this->assertDatabaseHas('users', [
        'id' => $account->id,
        'hourly_rate' => '125.50',
    ]);
});

it('assigns the selected team and its network to a newly registered participant', function () {
    $owner = User::factory()->create();
    $directorate = Directorate::create(['name' => 'Rede Leste', 'code' => 'REDE-LESTE']);
    $team = Team::create([
        'directorate_id' => $directorate->id,
        'name' => 'Equipe de Campo',
        'code' => 'EQUIPE-CAMPO',
    ]);

    $this->actingAs($owner)
        ->get(route('participants.create'))
        ->assertOk()
        ->assertSee('Equipe / rede')
        ->assertSee('Equipe de Campo — Rede Leste');

    $this->actingAs($owner)
        ->post(route('participants.store'), [
            'name' => 'João Silva',
            'email' => 'joao@example.com',
            'password' => 'Senha@123',
            'status' => 'active',
            'team_id' => $team->id,
        ])
        ->assertRedirect(route('participants.index'));

    $member = User::where('email', 'joao@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'team_id' => $team->id,
        'directorate_id' => $directorate->id,
    ]);
});

it('updates the linked participant account team and network', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create(['role' => 'participant']);
    $oldNetwork = Directorate::create(['name' => 'Rede Antiga', 'code' => 'REDE-ANTIGA']);
    $oldTeam = Team::create([
        'directorate_id' => $oldNetwork->id,
        'name' => 'Equipe Antiga',
        'code' => 'EQUIPE-ANTIGA',
    ]);
    $newNetwork = Directorate::create(['name' => 'Rede Nova', 'code' => 'REDE-NOVA']);
    $newTeam = Team::create([
        'directorate_id' => $newNetwork->id,
        'name' => 'Equipe Nova',
        'code' => 'EQUIPE-NOVA',
    ]);
    $member->update(['team_id' => $oldTeam->id, 'directorate_id' => $oldNetwork->id]);
    $participant = Participant::create([
        'user_id' => $owner->id,
        'member_user_id' => $member->id,
        'name' => $member->name,
        'email' => $member->email,
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get(route('participants.edit', $participant))
        ->assertOk()
        ->assertSee('Equipe / rede')
        ->assertSee('Equipe Nova — Rede Nova');

    $response = $this->actingAs($owner)
        ->put(route('participants.update', $participant), [
            'name' => $member->name,
            'email' => $member->email,
            'member_user_id' => $member->id,
            'team_id' => $newTeam->id,
            'status' => 'active',
            'type' => 'internal',
            'hourly_rate' => '100.00',
        ]);

    $response->assertRedirect(route('participants.index'));

    $this->assertDatabaseHas('users', [
        'id' => $member->id,
        'team_id' => $newTeam->id,
        'directorate_id' => $newNetwork->id,
    ]);
});

it('rejects inactive teams when assigning a participant', function () {
    $owner = User::factory()->create();
    $directorate = Directorate::create(['name' => 'Rede Inativa', 'code' => 'REDE-INATIVA']);
    $team = Team::create([
        'directorate_id' => $directorate->id,
        'name' => 'Equipe Inativa',
        'code' => 'EQUIPE-INATIVA',
        'active' => false,
    ]);

    $this->actingAs($owner)
        ->from(route('participants.create'))
        ->post(route('participants.store'), [
            'name' => 'Participante Teste',
            'email' => 'teste@example.com',
            'password' => 'Senha@123',
            'status' => 'active',
            'team_id' => $team->id,
        ])
        ->assertSessionHasErrors('team_id');

    $this->assertDatabaseMissing('users', ['email' => 'teste@example.com']);
});

it('only shows the authenticated user participants and searches by name', function () {
    $owner = User::factory()->create(['name' => 'Ana Souza']);
    $other = User::factory()->create(['name' => 'Carlos Lima']);

    Participant::create([
        'user_id' => $owner->id,
        'name' => 'Ana Souza',
        'email' => 'ana@empresa.com',
        'phone' => '11999999999',
        'status' => 'active',
    ]);

    Participant::create([
        'user_id' => $other->id,
        'name' => 'Carlos Lima',
        'email' => 'carlos@empresa.com',
        'phone' => '11888888888',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get('/participants?search=Ana')
        ->assertOk()
        ->assertSee('Ana Souza')
        ->assertDontSee('Carlos Lima');
});

it('shows the participant supervisors in the management table', function () {
    $owner = User::factory()->create(['name' => 'Maria Colaboradora', 'role' => 'participant']);
    $manager = User::factory()->create(['name' => 'Carlos Gerente', 'role' => 'manager']);
    $owner->supervisors()->attach($manager->id, ['relationship_type' => 'manager_collaborator']);

    Participant::create([
        'user_id' => $owner->id,
        'name' => 'Maria Colaboradora',
        'email' => 'maria@empresa.com',
        'status' => 'active',
    ]);

    $this->actingAs($owner)
        ->get('/participants')
        ->assertOk()
        ->assertSee('Superior responsável')
        ->assertSee('Carlos Gerente');
});

it('shows the authenticated user supervisors in the profile page', function () {
    $user = User::factory()->create(['name' => 'Maria Colaboradora', 'role' => 'participant']);
    $manager = User::factory()->create(['name' => 'Carlos Gerente', 'role' => 'manager']);
    $user->supervisors()->attach($manager->id, ['relationship_type' => 'manager_collaborator']);

    $this->actingAs($user)
        ->get('/profile')
        ->assertOk()
        ->assertSee('Superiores responsáveis')
        ->assertSee('Carlos Gerente');
});

it('registering a user also creates a participant profile for the same account', function () {
    $this->post('/register', [
        'name' => 'Ana Souza',
        'email' => 'ana@empresa.com',
        'password' => 'Senha@123',
        'password_confirmation' => 'Senha@123',
    ])->assertRedirect('/dashboard');

    $user = User::where('email', 'ana@empresa.com')->firstOrFail();

    $this->assertDatabaseHas('participants', [
        'user_id' => $user->id,
        'member_user_id' => $user->id,
        'name' => 'Ana Souza',
        'email' => 'ana@empresa.com',
        'status' => 'active',
    ]);
});
