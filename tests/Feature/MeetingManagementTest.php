<?php

use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects the home page to the login page', function () {
    $this->get('/')
        ->assertRedirect('/login');
});

it('user can view meeting management page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/meetings')
        ->assertOk();
});

it('user can create a meeting', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/meetings', [
            'title' => 'Reunião de alinhamento',
            'date' => '2026-09-10',
            'time' => '09:30',
            'location' => 'Sala 2',
            'status' => 'scheduled',
            'notes' => 'Definir metas do sprint.',
        ])
        ->assertRedirect('/meetings');

    $this->assertDatabaseHas('meetings', [
        'title' => 'Reunião de alinhamento',
        'location' => 'Sala 2',
        'status' => 'scheduled',
    ]);
});

it('user can attach participants to a meeting', function () {
    $user = User::factory()->create();
    $participantA = Participant::create([
        'user_id' => $user->id,
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'status' => 'active',
    ]);
    $participantB = Participant::create([
        'user_id' => $user->id,
        'name' => 'Bruno Lima',
        'email' => 'bruno@example.com',
        'status' => 'pending',
    ]);

    $this->actingAs($user)
        ->post('/meetings', [
            'title' => 'Sprint review',
            'date' => '2026-09-15',
            'time' => '15:00',
            'location' => 'Sala 3',
            'status' => 'scheduled',
            'participants' => [$participantA->id, $participantB->id],
        ])
        ->assertRedirect('/meetings');

    $meeting = \App\Models\Meeting::where('title', 'Sprint review')->first();

    $this->assertCount(2, $meeting->participants);
    $this->assertTrue($meeting->participants()->whereKey($participantA->id)->exists());
    $this->assertTrue($meeting->participants()->whereKey($participantB->id)->exists());
});

it('does not add the general administrator as a meeting participant', function () {
    $user = User::factory()->create();
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->post('/meetings', [
            'title' => 'Reunião sem administrador',
            'date' => '2026-09-15',
            'status' => 'scheduled',
            'participants' => [$admin->id],
        ])
        ->assertRedirect('/meetings');

    $meeting = \App\Models\Meeting::where('title', 'Reunião sem administrador')->firstOrFail();

    expect($meeting->users)->toBeEmpty();
});
