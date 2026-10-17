<?php

use App\Models\Meeting;
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

it('dashboard lists only meetings created by the signed-in user', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião da minha equipe',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    Meeting::create([
        'user_id' => $otherUser->id,
        'title' => 'Reunião de outra equipe',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Reunião da minha equipe')
        ->assertDontSee('Reunião de outra equipe')
        ->assertSee('Iniciar cronômetro');
});

it('uses the participant profile hourly rate for the running meeting cost', function () {
    $owner = User::factory()->create();
    $attendee = User::factory()->create(['hourly_rate' => null]);
    $participantProfile = Participant::create([
        'user_id' => $owner->id,
        'member_user_id' => $attendee->id,
        'name' => $attendee->name,
        'email' => $attendee->email,
        'status' => 'active',
        'hourly_rate' => 120,
    ]);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião com custo por hora',
        'date' => '2026-10-16',
        'status' => 'ongoing',
    ]);

    $meeting->users()->attach($attendee->id, ['attendance_status' => 'confirmed']);
    $meeting->participants()->attach($participantProfile->id, ['attendance_status' => 'confirmed']);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertViewHas('dashboardMeetings', function ($meetings) use ($meeting): bool {
            return $meetings->firstWhere('id', $meeting->id)['attendees'][0]['hourlyRate'] === 120.0;
        });
});

it('starts and pauses a meeting timer while persisting its elapsed time', function () {
    $owner = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião para cronometrar',
        'date' => '2026-10-16',
        'duration_minutes' => 60,
        'actual_duration_seconds' => 15,
        'status' => 'scheduled',
    ]);

    $this->travelTo(now()->startOfSecond());

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertOk()
        ->assertJsonPath('actualDurationSeconds', 15)
        ->assertJsonPath('timerStartedAt', now()->toIso8601String());

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Reunião para cronometrar')
        ->assertSee('O cronômetro continua contando mesmo se você atualizar a página.');

    $this->travel(75)->seconds();

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.stop', $meeting))
        ->assertOk()
        ->assertJsonPath('actualDurationSeconds', 90)
        ->assertJsonPath('timerStartedAt', null);

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'actual_duration_seconds' => 90,
        'timer_started_at' => null,
        'status' => 'ongoing',
    ]);

    $this->travelBack();
});

it('finishes a running meeting, saves its final time and includes the final cost in reports', function () {
    $this->travelTo('2026-10-16 10:00:00');

    $owner = User::factory()->create(['role' => 'executive', 'hourly_rate' => 120]);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião finalizada no cronômetro',
        'date' => '2026-10-16',
        'actual_duration_seconds' => 15,
        'timer_started_at' => now()->startOfSecond(),
        'status' => 'ongoing',
    ]);
    $meeting->users()->attach($owner->id, ['attendance_status' => 'confirmed']);

    $this->travel(75)->seconds();

    $this->actingAs($owner)
        ->postJson(route('meetings.finish', $meeting))
        ->assertOk()
        ->assertJsonPath('actualDurationSeconds', 90)
        ->assertJsonPath('timerStartedAt', null)
        ->assertJsonPath('status', 'completed');

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'actual_duration_seconds' => 90,
        'timer_started_at' => null,
        'status' => 'completed',
    ]);

    $this->actingAs($owner)
        ->getJson(route('reports.meetings'))
        ->assertOk()
        ->assertJsonPath('rows.0.actual_hours', 0.03)
        ->assertJsonPath('rows.0.real_cost', 3);

    $this->travelBack();
});

it('prevents users from starting another users meeting timer', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião privada',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    $this->actingAs($otherUser)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->postJson(route('meetings.timer.stop', $meeting))
        ->assertForbidden();

    $this->actingAs($otherUser)
        ->postJson(route('meetings.finish', $meeting))
        ->assertForbidden();

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'timer_started_at' => null,
        'status' => 'scheduled',
    ]);
});

it('requires authentication to start a meeting timer', function () {
    $owner = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião restrita',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    $this->postJson(route('meetings.timer.start', $meeting))
        ->assertUnauthorized();
});

it('does not start a canceled meeting timer', function () {
    $owner = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião cancelada',
        'date' => '2026-10-16',
        'status' => 'canceled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertStatus(422);

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'timer_started_at' => null,
        'status' => 'canceled',
    ]);
});

it('does not stop a timer that is not running', function () {
    $owner = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião sem cronômetro',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.stop', $meeting))
        ->assertStatus(409);

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'actual_duration_seconds' => null,
        'timer_started_at' => null,
        'status' => 'scheduled',
    ]);
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

    $meeting = Meeting::where('title', 'Reunião com chamada')->firstOrFail();

    expect($meeting->duration_minutes)->toBe(90)
        ->and($meeting->actual_duration_seconds)->toBe(3665);

    $this->assertDatabaseHas('meeting_user', [
        'meeting_id' => $meeting->id,
        'user_id' => $attendee->id,
        'attendance_status' => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->get('/meetings/'.$meeting->id.'/edit')
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
        ->assertSessionHasErrors('attendance.'.$attendee->id);
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

    $meeting = Meeting::where('title', 'Sprint review')->first();

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

    $meeting = Meeting::where('title', 'Reunião sem administrador')->firstOrFail();

    expect($meeting->users)->toBeEmpty();
});
