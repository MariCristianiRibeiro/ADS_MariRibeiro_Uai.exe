<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('new user registers as participant pending financial setup', function () {
    $this->post('/register', [
        'name' => 'Maria Participante',
        'email' => 'maria@empresa.com',
        'password' => 'Senha@123',
        'password_confirmation' => 'Senha@123',
    ])->assertRedirect('/dashboard');

    $this->assertDatabaseHas('users', [
        'email' => 'maria@empresa.com',
        'role' => 'participant',
        'status' => 'pending_finance',
    ]);
});

it('only administrators can access user management', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $participant = User::factory()->create(['role' => 'participant', 'status' => 'pending_finance']);

    $this->actingAs($participant)
        ->get('/users')
        ->assertForbidden();

    $this->actingAs($admin)
        ->get('/users')
        ->assertOk();
});

it('administrators can search and filter users', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    User::factory()->create([
        'name' => 'Maria Participante',
        'email' => 'maria@example.com',
        'role' => 'participant',
        'status' => 'pending_finance',
    ]);
    User::factory()->create([
        'name' => 'Carlos Administrador',
        'email' => 'carlos@example.com',
        'role' => 'admin',
        'status' => 'active',
    ]);

    $this->actingAs($admin)
        ->get('/users?search=Maria&status=pending_finance&role=participant')
        ->assertOk()
        ->assertSee('Maria Participante')
        ->assertDontSee('Carlos Administrador');
});

it('user can update user status from management screen', function () {
    $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
    $user = User::factory()->create([ 'name' => 'Joao', 'email' => 'joao@example.com', 'role' => 'participant', 'status' => 'pending_finance' ]);

    $this->actingAs($admin)
        ->put('/users/' . $user->id, [
            'name' => 'Joao Atualizado',
            'email' => 'joao@example.com',
            'status' => 'active',
            'role' => 'participant',
            'hourly_rate' => '150.00',
            'salary' => '7500.00',
        ])
        ->assertRedirect('/users');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Joao Atualizado',
        'status' => 'active',
        'role' => 'participant',
    ]);
});
