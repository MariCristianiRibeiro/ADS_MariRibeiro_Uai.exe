<?php

use App\Models\Participant;
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
            'phone' => '11999999999',
            'status' => 'active',
        ])
        ->assertRedirect('/participants');

    $this->assertDatabaseHas('participants', [
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'status' => 'active',
    ]);
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
        'name' => 'Ana Souza',
        'email' => 'ana@empresa.com',
        'status' => 'active',
    ]);
});
