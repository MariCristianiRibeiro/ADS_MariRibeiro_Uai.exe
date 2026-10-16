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

it('stores actual meeting time and attendance confirmation separately from planned time', function () {
    $owner = User::factory()->create(['role' => 'manager']);
    $attendee = User::factory()->create(['role' => 'participant']);

    $this->actingAs($owner)
        ->post('/meetings', [
            'title' => 'Reunião com chamada',
            'date' => '2026-09-15',
            'duration_minutes' => 90,
            'actual_duration_seconds' => 3665,
            'status' => 'completed',
            'participants' => [$attendee->id],
            'attendance' => [$attendee->id => 'confirmed'],
        ])
        ->assertRedirect('/meetings');

    $meeting = \App\Models\Meeting::where('title', 'Reunião com chamada')->firstOrFail();

    expect($meeting->duration_minutes)->toBe(90)
        ->and($meeting->actual_duration_seconds)->toBe(3665);

    $this->assertDatabaseHas('meeting_user', [
        'meeting_id' => $meeting->id,
        'user_id' => $attendee->id,
        'attendance_status' => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->get('/meetings/' . $meeting->id . '/edit')
        ->assertOk()
        ->assertSee('Chamada de presença')
        ->assertSee('Presença confirmada')
        ->assertSee('Tempo realizado')
        ->assertDontSee('Pausar');

    $this->actingAs($owner)
        ->get('/meetings/create')
        ->assertOk()
        ->assertSee('Duração prevista (minutos)')
        ->assertSee('Iniciar cronômetro')
        ->assertDontSee('Pausar');
});

it('rejects invalid attendance statuses', function () {
    $owner = User::factory()->create(['role' => 'manager']);
    $attendee = User::factory()->create(['role' => 'participant']);

    $this->actingAs($owner)
        ->post('/meetings', [
            'title' => 'Reunião com status inválido',
            'date' => '2026-09-15',
            'status' => 'scheduled',
            'participants' => [$attendee->id],
            'attendance' => [$attendee->id => 'maybe'],
        ])
        ->assertSessionHasErrors('attendance.' . $attendee->id);
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
