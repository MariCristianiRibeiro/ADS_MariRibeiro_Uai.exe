<?php

use App\Models\Meeting;
use App\Models\Participant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('redirects the home page to the login page', function () {
    $this->get('/')
        ->assertRedirect('/login');
});

it('user can view meeting management page', function () {
    $user = createUserWithPermissions(['meetings.view']);

    $this->actingAs($user)
        ->get('/meetings')
        ->assertOk();
});

it('dashboard lists only meetings created by the signed-in user', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $otherUser = User::factory()->create();

    Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião da minha equipe',
        'date' => '2026-12-16',
        'status' => 'scheduled',
    ]);

    Meeting::create([
        'user_id' => $otherUser->id,
        'title' => 'Reunião de outra equipe',
        'date' => '2026-12-16',
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Reunião da minha equipe')
        ->assertDontSee('Reunião de outra equipe');
});

it('shows scheduled upcoming and ongoing meetings in the dashboard selector', function () {
    $this->travelTo('2026-11-14 14:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $scheduledToday = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda de hoje',
        'date' => '2026-11-14',
        'status' => 'scheduled',
    ]);
    $scheduledUpcoming = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda futura',
        'date' => '2026-11-15',
        'status' => 'scheduled',
    ]);
    $ongoing = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda em andamento',
        'date' => '2026-11-13',
        'status' => 'ongoing',
    ]);
    $pastScheduled = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda vencida',
        'date' => '2026-11-13',
        'status' => 'scheduled',
    ]);
    Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda já concluída',
        'date' => '2026-11-15',
        'status' => 'completed',
    ]);
    Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda em rascunho',
        'date' => '2026-11-15',
        'status' => 'draft',
    ]);

    $response = $this->actingAs($owner)->get(route('dashboard'))->assertOk();

    $response->assertViewHas('dashboardMeetings', function ($meetings) use ($scheduledToday, $scheduledUpcoming, $ongoing): bool {
        return $meetings->pluck('id')->sort()->values()->all() === collect([
            $scheduledToday->id,
            $scheduledUpcoming->id,
            $ongoing->id,
        ])->sort()->values()->all();
    });
    $response->assertViewHas('pastMeetings', fn ($meetings): bool => $meetings->pluck('id')->all() === [$pastScheduled->id]);

    $this->travelBack();
});

it('uses the participant profile hourly rate for the running meeting cost', function () {
    $owner = createUserWithPermissions(['reports.costs']);
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
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião para cronometrar',
        'date' => now('America/Sao_Paulo')->toDateString(),
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

it('returns 422 when starting a meeting scheduled on a past date', function () {
    $this->travelTo('2026-11-14 10:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Agenda vencida',
        'date' => '2026-11-13',
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Esta agenda tem uma data passada. Reagende-a ou registre o encerramento com o motivo antes de continuar.');

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'timer_started_at' => null,
        'status' => 'scheduled',
        'date' => '2026-11-13 00:00:00',
    ]);

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Encerrar agenda')
        ->assertSee('Reagendar agenda');

    $this->actingAs($owner)
        ->get(route('meetings.closeout', $meeting))
        ->assertOk()
        ->assertSee('Por que a reunião não aconteceu na data planejada?')
        ->assertSee('Indicadores adicionais (opcional)')
        ->assertDontSee('<pre');

    $this->travelBack();
});

it('confirms an early start, reschedules the meeting, and requires the advance reason at conclusion', function () {
    config(['services.ai.providers.gemini.api_key' => null]);
    $this->travelTo('2026-11-14 10:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit', 'meetings.finish']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Agenda futura',
        'date' => '2026-11-16',
        'time' => '09:00',
        'end_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertStatus(409)
        ->assertJsonPath('code', 'advance_confirmation_required');

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'date' => '2026-11-16 00:00:00',
        'status' => 'scheduled',
        'timer_started_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting), ['confirm_advance' => true])
        ->assertOk()
        ->assertJsonPath('scheduledDate', '2026-11-14')
        ->assertJsonPath('scheduledTime', '07:00');

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'date' => '2026-11-14 00:00:00',
        'time' => '07:00',
        'end_time' => '08:00',
        'status' => 'ongoing',
    ]);
    expect($meeting->fresh()->schedule_advanced_at)->not->toBeNull();

    $this->actingAs($owner)
        ->postJson(route('meetings.finish', $meeting))
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('redirectUrl', route('meetings.closeout', $meeting));

    $conclusion = [
        'summary' => 'A reunião foi antecipada e concluída.',
        'outcome' => 'A equipe aprovou o plano de trabalho.',
        'objective_achievement' => 'yes',
    ];

    $this->actingAs($owner)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion)
        ->assertInvalid([
            'schedule_change_reason' => 'Informe o motivo da antecipação ou da não realização na data planejada.',
        ]);

    $this->actingAs($owner)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion + [
            'schedule_change_reason' => 'Uma decisão urgente exigiu reunir a equipe antes.',
        ])
        ->assertRedirect(route('meetings.show', $meeting))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('meeting_conclusions', [
        'meeting_id' => $meeting->id,
        'schedule_change_reason' => 'Uma decisão urgente exigiu reunir a equipe antes.',
    ]);

    $this->travelBack();
});

it('redirects an early finished meeting to its details when the timer user is not the organizer', function () {
    $this->travelTo('2026-11-14 10:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit', 'meetings.finish']);
    $organizer = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $organizer->id,
        'title' => 'Agenda futura de outra pessoa',
        'date' => '2026-11-16',
        'time' => '09:00',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting), ['confirm_advance' => true])
        ->assertOk();

    $this->actingAs($owner)
        ->postJson(route('meetings.finish', $meeting))
        ->assertOk()
        ->assertJsonPath('redirectUrl', route('meetings.show', $meeting));

    $this->travelBack();
});

it('confirms a late start without changing the scheduled date or time', function () {
    $this->travelTo('2026-11-14 15:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Agenda com início atrasado',
        'date' => '2026-11-14',
        'time' => '11:00',
        'end_time' => '12:00',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertStatus(409)
        ->assertJsonPath('code', 'late_start_confirmation_required')
        ->assertJsonPath('message', 'O horário previsto desta agenda já passou. Deseja iniciar o cronômetro agora? A data e o horário planejados serão mantidos.');

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'date' => '2026-11-14 00:00:00',
        'time' => '11:00',
        'status' => 'scheduled',
        'timer_started_at' => null,
    ]);

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting), ['confirm_late_start' => true])
        ->assertOk()
        ->assertJsonPath('scheduledDate', '2026-11-14')
        ->assertJsonPath('scheduledTime', '11:00')
        ->assertJsonPath('timerStartedAt', now()->toIso8601String());

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.stop', $meeting))
        ->assertOk();

    $this->actingAs($owner)
        ->postJson(route('meetings.timer.start', $meeting))
        ->assertOk();

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'date' => '2026-11-14 00:00:00',
        'time' => '11:00',
        'status' => 'ongoing',
        'schedule_advanced_at' => null,
    ]);

    $this->travelBack();
});

it('accumulates separate running intervals when a meeting timer is paused and resumed', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'title' => 'Reunião com pausa',
        'date' => '2026-10-16',
        'status' => 'scheduled',
    ]);

    $this->travelTo('2026-10-16 10:00:00');
    $this->actingAs($owner)->postJson(route('meetings.timer.start', $meeting))->assertOk();
    $this->travel(75)->seconds();
    $this->actingAs($owner)->postJson(route('meetings.timer.stop', $meeting))
        ->assertOk()
        ->assertJsonPath('actualDurationSeconds', 75);

    $this->actingAs($owner)->postJson(route('meetings.timer.start', $meeting))->assertOk();
    $this->travel(45)->seconds();
    $this->actingAs($owner)->postJson(route('meetings.timer.stop', $meeting))
        ->assertOk()
        ->assertJsonPath('actualDurationSeconds', 120);

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'actual_duration_seconds' => 120,
        'timer_started_at' => null,
    ]);

    $this->travelBack();
});

it('finishes a running meeting, saves its final time and includes the final cost in reports', function () {
    $this->travelTo('2026-10-16 10:00:00');

    $owner = createUserWithPermissions(
        ['meetings.view', 'meetings.finish', 'reports.view', 'reports.costs'],
        ['role' => 'executive', 'hourly_rate' => 120],
    );
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
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('redirectUrl', route('meetings.show', $meeting));

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
    $otherUser = createUserWithPermissions(['meetings.view', 'meetings.edit', 'meetings.finish']);
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
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
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
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
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

it('requires a reason and conclusion data before closing a past unstarted meeting', function () {
    config(['services.ai.providers.gemini.api_key' => null]);
    $this->travelTo('2026-11-14 10:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Agenda sem realização',
        'date' => '2026-11-13',
        'status' => 'scheduled',
    ]);
    $conclusion = [
        'summary' => 'A agenda não ocorreu na data prevista.',
        'outcome' => 'A pauta foi transferida para outro encontro.',
        'objective_achievement' => 'no',
    ];

    $this->actingAs($owner)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Motivo da não realização na data planejada')
        ->assertDontSee('<pre');

    $this->actingAs($owner)
        ->get(route('meetings.closeout', $meeting))
        ->assertOk()
        ->assertSee('Conclusão da reunião')
        ->assertDontSee('<pre');

    $this->actingAs($owner)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion)
        ->assertInvalid([
            'schedule_change_reason' => 'Informe o motivo da antecipação ou da não realização na data planejada.',
        ]);

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion + [
            'schedule_change_reason' => 'Os participantes-chave tiveram um imprevisto.',
        ])
        ->assertRedirect(route('meetings.show', $meeting))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('meetings', [
        'id' => $meeting->id,
        'status' => 'completed',
        'actual_duration_seconds' => null,
    ]);
    $this->assertDatabaseHas('meeting_conclusions', [
        'meeting_id' => $meeting->id,
        'schedule_change_reason' => 'Os participantes-chave tiveram um imprevisto.',
    ]);

    $this->travelBack();
});

it('user can create a meeting', function () {
    $user = createUserWithPermissions(['meetings.view', 'meetings.create']);

    postMeetingAfterPreview($this, $user, [
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

it('derives planned meeting duration from its start and end times', function () {
    $user = createUserWithPermissions(['meetings.view', 'meetings.create']);

    postMeetingAfterPreview($this, $user, [
        'title' => 'Reunião de quinze minutos',
        'date' => '2026-09-10',
        'time' => '21:45',
        'end_time' => '22:00',
        'duration_minutes' => 60,
    ])->assertRedirect('/meetings');

    $this->assertDatabaseHas('meetings', [
        'user_id' => $user->id,
        'title' => 'Reunião de quinze minutos',
        'duration_minutes' => 15,
    ]);
});

it('includes participant names in the preflight preview', function () {
    $user = createUserWithPermissions(['meetings.view', 'meetings.create', 'reports.costs']);
    $attendee = User::factory()->create(['name' => 'Ana Souza', 'role' => 'participant']);

    $this->actingAs($user)
        ->post(route('meetings.preview'), [
            'title' => 'Reunião de revisão',
            'date' => '2026-09-10',
            'duration_minutes' => 60,
            'participants' => [$attendee->id],
            'participant_roles' => [$attendee->id => 'participant'],
            'organizer_id' => $user->id,
            'priority' => 'normal',
            'recurrence_type' => 'single',
            'status' => 'scheduled',
        ])
        ->assertOk()
        ->assertSee('Ana Souza');
});

it('shows Gemini preflight insights and sends calculated two-additional-meeting scenarios as context', function () {
    config([
        'services.ai.provider' => 'gemini',
        'services.ai.providers.gemini.api_key' => 'test-api-key',
    ]);
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
            'modelVersion' => 'gemini-preflight-test',
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'summary' => 'O objetivo pode ser avaliado para alinhamento assíncrono.',
                        'findings' => ['A pauta deve orientar se a reunião é necessária.'],
                        'recommendations' => [[
                            'title' => 'Avaliar alinhamento por e-mail',
                            'detail' => 'Use e-mail se a pauta exigir apenas compartilhamento de informações.',
                            'justification' => 'A decisão depende da natureza da pauta.',
                        ]],
                        'risks' => [],
                        'opportunities' => [],
                        'missing_information' => [],
                        'confidence' => 0.9,
                        'historical_basis' => [],
                        'suggested_next_steps' => ['Confirmar se os participantes precisam deliberar juntos.'],
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]]],
            ]],
        ]),
    ]);

    $owner = createUserWithPermissions(['meetings.view', 'meetings.create', 'reports.costs']);
    $higherRateAttendee = User::factory()->create(['hourly_rate' => 200]);
    $lowerRateAttendee = User::factory()->create(['hourly_rate' => 100]);

    $response = $this->actingAs($owner)->post(route('meetings.preview'), [
        'title' => 'Alinhamento de projeto',
        'objective' => 'Definir próximos passos do projeto.',
        'agenda' => 'Revisar as entregas e decidir prioridades.',
        'expected_outcome' => 'Prioridades definidas.',
        'date' => '2026-12-10',
        'duration_minutes' => 60,
        'participants' => [$higherRateAttendee->id, $lowerRateAttendee->id],
        'participant_roles' => [
            $higherRateAttendee->id => 'decision_responsible',
            $lowerRateAttendee->id => 'participant',
        ],
        'organizer_id' => $owner->id,
        'priority' => 'normal',
        'recurrence_type' => 'single',
        'status' => 'scheduled',
    ])->assertOk()
        ->assertSee('Resposta real da IA')
        ->assertSee('GEMINI / gemini-preflight-test')
        ->assertSee('Avaliar alinhamento por e-mail')
        ->assertSee('2 reuniões adicionais iguais')
        ->assertSee('R$ 600,00')
        ->assertSee('R$ 900,00')
        ->assertSee('R$ 300,00');
    $response->assertSeeInOrder([
        'Resumo executivo da agenda',
        'O objetivo pode ser avaliado para alinhamento assíncrono.',
        'Avaliar alinhamento por e-mail',
        'Use e-mail se a pauta exigir apenas compartilhamento de informações.',
    ])->assertDontSee('a decisão pode ser compartilhada por e-mail');

    Http::assertSent(function ($request): bool {
        $prompt = json_decode($request->data()['contents'][0]['parts'][0]['text'], true, flags: JSON_THROW_ON_ERROR);
        $metrics = $prompt['contexto_real']['indicadores_calculados_pelo_sistema'];

        return str_contains($request->url(), ':generateContent')
            && $metrics['custo_estimado'] === 300
            && $metrics['custo_duas_reunioes_adicionais'] === 600
            && $metrics['custo_tres_reunioes_total'] === 900
            && $metrics['economia_retirada_participante_em_tres_reunioes'] === 300;
    });
});

it('hides calculated meeting costs from preflight viewers without cost permission', function () {
    config([
        'services.ai.provider' => 'gemini',
        'services.ai.providers.gemini.api_key' => 'test-api-key',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
            'modelVersion' => 'gemini-preflight-test',
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'summary' => 'A pauta exige deliberação em conjunto.',
                        'findings' => [],
                        'recommendations' => [],
                        'risks' => [],
                        'opportunities' => [],
                        'missing_information' => [],
                        'confidence' => 0.9,
                        'historical_basis' => [],
                        'suggested_next_steps' => [],
                    ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ]]],
            ]],
        ]),
    ]);

    $owner = createUserWithPermissions(['meetings.view', 'meetings.create']);
    $attendee = User::factory()->create(['hourly_rate' => 100]);

    $this->actingAs($owner)->post(route('meetings.preview'), [
        'title' => 'Deliberação de projeto',
        'objective' => 'Aprovar os próximos passos.',
        'agenda' => 'Decidir prioridade.',
        'date' => '2026-12-10',
        'duration_minutes' => 60,
        'participants' => [$attendee->id],
        'organizer_id' => $owner->id,
        'priority' => 'normal',
        'recurrence_type' => 'single',
        'status' => 'scheduled',
    ])->assertOk()
        ->assertSee('A pauta exige deliberação em conjunto.')
        ->assertDontSee('R$ 100,00')
        ->assertDontSee('R$ 300,00')
        ->assertDontSee('Economia estimada ao reduzir 1 participante');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), ':generateContent'));
});

it('stores actual meeting time and attendance confirmation separately from planned time', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create', 'meetings.edit'], ['role' => 'manager']);
    $attendee = User::factory()->create(['role' => 'participant']);

    postMeetingAfterPreview($this, $owner, [
        'title' => 'Reunião com chamada',
        'date' => '2026-09-15',
        'duration_minutes' => 90,

        'status' => 'ongoing',
        'participants' => [$attendee->id],
        'attendance' => [$attendee->id => 'confirmed'],
    ])
        ->assertRedirect('/meetings');

    $meeting = Meeting::where('title', 'Reunião com chamada')->firstOrFail();

    $this->travelTo('2026-09-15 09:00:00');
    $this->actingAs($owner)->postJson(route('meetings.timer.start', $meeting))->assertOk();
    $this->travel(3665)->seconds();
    $this->actingAs($owner)->postJson(route('meetings.timer.stop', $meeting))->assertOk();
    $meeting->refresh();
    $this->travelBack();

    expect($meeting->duration_minutes)->toBe(90)
        ->and($meeting->actual_duration_seconds)->toBe(3665);

    $this->assertDatabaseHas('meeting_participants', [
        'meeting_id' => $meeting->id,
        'user_id' => $attendee->id,
        'attendance_status' => 'confirmed',
    ]);

    $this->actingAs($owner)
        ->get('/meetings/'.$meeting->id.'/edit')
        ->assertOk()
        ->assertSee('Participantes')
        ->assertSee('Confirmação')
        ->assertSee('Investimento previsto')
        ->assertDontSee('Pausar');

    $this->actingAs($owner)
        ->get('/meetings/create')
        ->assertOk()
        ->assertSee('Duração prevista (minutos)')
        ->assertSee('Investimento previsto')
        ->assertDontSee('Pausar');
});

it('rejects invalid attendance statuses', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create'], ['role' => 'manager']);
    $attendee = User::factory()->create(['role' => 'participant']);

    postMeetingAfterPreview($this, $owner, [
        'title' => 'Reunião com status inválido',
        'date' => '2026-09-15',
        'status' => 'scheduled',
        'participants' => [$attendee->id],
        'attendance' => [$attendee->id => 'maybe'],
    ])
        ->assertSessionHasErrors('attendance.'.$attendee->id);
});

it('user can attach participants to a meeting', function () {
    $user = createUserWithPermissions(['meetings.view', 'meetings.create']);
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

    postMeetingAfterPreview($this, $user, [
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
    $user = createUserWithPermissions(['meetings.view', 'meetings.create']);
    $admin = createAdminUser();

    postMeetingAfterPreview($this, $user, [
        'title' => 'Reunião sem administrador',
        'date' => '2026-09-15',
        'status' => 'scheduled',
        'participants' => [$admin->id],
    ])
        ->assertRedirect('/meetings');

    $meeting = Meeting::where('title', 'Reunião sem administrador')->firstOrFail();

    expect($meeting->users)->toBeEmpty();
});
