<?php

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAnalysis;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantFeedback;
use Illuminate\Support\Facades\Http;

it('stores the structured meeting outcome and contextual participant observations', function () {
    config(['services.ai.providers.gemini.api_key' => 'test-api-key']);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'summary' => 'A reunião atingiu parcialmente o objetivo.', 'findings' => ['Há assuntos pendentes.'],
            'recommendations' => [], 'risks' => [], 'opportunities' => [], 'missing_information' => [],
            'confidence' => 0.8, 'historical_basis' => [], 'suggested_next_steps' => [],
        ], JSON_THROW_ON_ERROR)]]]]],
    ])]);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $participantUser = createUserWithPermissions(['meetings.view']);
    $historicalMeeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Revisão trimestral',
        'date' => '2026-11-08',
        'duration_minutes' => 60,
        'status' => 'completed',
        'recurrence_type' => 'recurring',
        'recurrence_frequency' => 'monthly',
    ]);
    $historicalMeeting->conclusion()->create([
        'recorded_by' => $owner->id,
        'summary' => 'Resultados trimestrais registrados.',
        'outcome' => 'Metas trimestrais atingidas.',
        'objective_achievement' => 'yes',
        'productivity_score' => 5,
        'concluded_at' => now(),
    ]);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Revisão trimestral', 'date' => '2026-11-09', 'duration_minutes' => 60, 'status' => 'completed']);
    $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $participantUser->id, 'attendance_status' => 'present']);

    $this->actingAs($owner)->put(route('meetings.conclusion.save', $meeting), [
        'summary' => 'Plano revisado.', 'outcome' => 'Cronograma aprovado.', 'objective_achievement' => 'partially',
        'decisions_count' => 2, 'completed_topics_count' => 3, 'pending_topics' => 'Aprovar orçamento',
        'objective_changed' => 0, 'duration_sufficient' => 1, 'time_well_used' => 1, 'replaceable_by_email' => 0,
        'needs_follow_up' => 1, 'productivity_score' => 4, 'investment_return_score' => 3,
        'participation' => [$participant->id => ['participation_effective' => 1, 'relevant_contribution' => 1, 'contributed_to_decision' => 0, 'participation_necessary' => 1, 'self_rating' => 4]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $analysis = MeetingAnalysis::query()->where('analysis_type', 'post_meeting')->firstOrFail();
    expect($analysis->status)->toBe('completed')
        ->and($analysis->input_data['resultado_informado']['assuntos_pendentes'])->toBe('Aprovar orçamento')
        ->and($analysis->input_data['historico_relacionado_acessivel'][0]['recorrencia']['frequencia'])->toBe('monthly')
        ->and($analysis->input_data['historico_relacionado_acessivel'][0]['fechamento_estruturado']['productivity_score'])->toBe(5);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generateContent'));

    $this->actingAs($participantUser)->put(route('meetings.participant-feedback.save', $meeting), [
        'clarity_score' => 5, 'objectivity_score' => 4, 'organization_score' => 4, 'time_management_score' => 3,
        'participation_score' => 4, 'effectiveness_score' => 4, 'suggestion' => 'Enviar materiais antes.', 'comments' => 'Boa conversa.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($meeting->fresh()->conclusion->objective_achievement)->toBe('partially')
        ->and($meeting->fresh()->conclusion->investment_return_score)->toBe(3)
        ->and($participant->fresh()->participation_effective)->toBeTrue()
        ->and(MeetingParticipantFeedback::query()->where('meeting_id', $meeting->id)->count())->toBe(1);
});

it('allows only the meeting organizer to record a conclusion after the timer is finished', function () {
    config(['services.ai.providers.gemini.api_key' => null]);
    Http::preventStrayRequests();
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $organizer = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $organizer->id,
        'title' => 'Revisão de resultados',
        'date' => '2026-11-09',
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);
    $conclusion = [
        'summary' => 'A pauta foi revisada com a equipe.',
        'outcome' => 'O cronograma foi aprovado pela equipe.',
        'objective_achievement' => 'yes',
    ];

    $this->actingAs($owner)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion)
        ->assertForbidden();

    $this->actingAs($organizer)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Salvar conclusão e gerar insights');

    $this->actingAs($organizer)
        ->put(route('meetings.conclusion.save', $meeting), $conclusion)
        ->assertRedirect()
        ->assertSessionHas('warning');

    expect($meeting->fresh()->conclusion->outcome)->toBe($conclusion['outcome']);
});

it('preserves the conclusion when automatic AI analysis fails', function () {
    config(['services.ai.providers.gemini.api_key' => 'test-api-key']);
    Http::preventStrayRequests();
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião de acompanhamento',
        'date' => '2026-11-09',
        'duration_minutes' => 30,
        'status' => 'completed',
    ]);

    $this->actingAs($owner)->put(route('meetings.conclusion.save', $meeting), [
        'summary' => 'Os principais pontos foram revisados.',
        'outcome' => 'A equipe definiu as próximas ações.',
        'objective_achievement' => 'partially',
    ])->assertRedirect()->assertSessionHas('warning');

    expect($meeting->fresh()->conclusion->outcome)->toBe('A equipe definiu as próximas ações.')
        ->and(MeetingAnalysis::query()->where('analysis_type', 'post_meeting')->firstOrFail()->status)->toBe('failed');
});

it('does not allow recording a conclusion before the meeting is completed', function () {
    config(['services.ai.providers.gemini.api_key' => null]);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião em andamento',
        'date' => '2026-11-09',
        'duration_minutes' => 30,
        'status' => 'ongoing',
    ]);

    $this->actingAs($owner)->put(route('meetings.conclusion.save', $meeting), [
        'summary' => 'Os principais pontos foram revisados.',
        'outcome' => 'A equipe definiu as próximas ações.',
        'objective_achievement' => 'partially',
    ])->assertStatus(409);

    expect($meeting->fresh()->conclusion)->toBeNull();
});

it('preserves each action item change including completion, lateness, and reopening', function () {
    config(['services.ai.providers.gemini.api_key' => null]);
    Http::preventStrayRequests();
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Reunião de projeto', 'date' => '2026-11-09', 'duration_minutes' => 45, 'status' => 'completed']);
    $action = MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Entregar relatório', 'description' => 'Consolidar dados.', 'due_date' => now()->subDay()->toDateString(), 'status' => 'open']);

    $this->actingAs($owner)->patch(route('meetings.action-items.update', [$meeting, $action]), ['status' => 'completed', 'delivered_result' => 'Relatório entregue', 'delay_reason' => 'Dados chegaram depois', 'needs_additional_meeting' => 1])->assertRedirect()->assertSessionHasNoErrors();
    $this->actingAs($owner)->patch(route('meetings.action-items.update', [$meeting, $action]), ['status' => 'in_progress', 'delivered_result' => 'Reaberto para ajuste'])->assertRedirect()->assertSessionHasNoErrors();

    $action->refresh();
    expect($action->status)->toBe('in_progress')
        ->and($action->completed_at)->toBeNull()
        ->and($action->history()->where('event', 'completed')->exists())->toBeTrue()
        ->and($action->history()->where('event', 'reopened')->exists())->toBeTrue()
        ->and($action->needs_additional_meeting)->toBeTrue();
});

it('keeps initial analysis immutable and feeds it with final action outcomes for a complementary AI analysis', function () {
    config(['services.gemini.api_key' => 'test-api-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'summary' => 'A previsão foi comparada ao resultado.', 'findings' => ['Uma demanda foi concluída.'],
            'recommendations' => [], 'risks' => [], 'opportunities' => [], 'missing_information' => [],
            'confidence' => 0.8, 'historical_basis' => [], 'suggested_next_steps' => [],
        ], JSON_THROW_ON_ERROR)]]]]],
    ])]);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Reunião de implantação', 'objective' => 'Planejar implantação', 'date' => '2026-11-09', 'duration_minutes' => 60, 'status' => 'completed']);
    $forecast = $meeting->analyses()->create(['requested_by' => $owner->id, 'analysis_type' => 'pre_meeting', 'version' => 1, 'input_data' => ['forecast' => ['duration_minutes' => 60]], 'transcript' => '', 'status' => 'completed', 'provider' => 'gemini', 'model' => 'test']);
    $action = MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Preparar plano', 'description' => 'Criar plano de implantação.', 'due_date' => now()->addDay()->toDateString(), 'status' => 'completed', 'completed_at' => now(), 'delivered_result' => 'Plano aprovado']);

    $this->actingAs($owner)->post(route('meetings.analyses.store', $meeting), ['analysis_type' => 'follow_up', 'analysis_notes' => 'O plano foi entregue e aprovado pela equipe.'])->assertRedirect()->assertSessionHasNoErrors();

    $followUp = MeetingAnalysis::query()->where('analysis_type', 'follow_up')->firstOrFail();
    expect($followUp->input_data['previsao_inicial_preservada']['dados_utilizados'])->toBe(['forecast' => ['duration_minutes' => 60]])
        ->and($followUp->input_data['encaminhamentos_registrados'][0]['resultado_entregue'])->toBe('Plano aprovado')
        ->and($forecast->fresh()->input_data)->toBe(['forecast' => ['duration_minutes' => 60]]);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generateContent'));
});

it('does not allow a complementary analysis while action items are still pending', function () {
    config(['services.gemini.api_key' => 'test-api-key']);
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Reunião pendente', 'date' => '2026-11-09', 'duration_minutes' => 30, 'status' => 'completed']);
    MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Validar entrega', 'description' => 'Aguardar retorno.', 'status' => 'open']);

    $this->actingAs($owner)->post(route('meetings.analyses.store', $meeting), ['analysis_type' => 'follow_up', 'analysis_notes' => 'Ainda aguardamos a validação da entrega.'])->assertSessionHasErrors('analysis_notes');
    expect(MeetingAnalysis::query()->count())->toBe(0);
});

it('renders the closeout and learning history for a completed meeting', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Revisão da operação', 'date' => '2026-11-09', 'duration_minutes' => 30, 'actual_duration_seconds' => 1800, 'status' => 'completed']);
    $meeting->conclusion()->create(['recorded_by' => $owner->id, 'outcome' => 'Plano validado', 'objective_achievement' => 'yes', 'concluded_at' => now()]);

    $this->actingAs($owner)->get(route('meetings.show', $meeting))->assertOk()
        ->assertSee('Histórico de aprendizado')
        ->assertSee('Objetivo alcançado')
        ->assertSee('Plano validado')
        ->assertSee('Gerar análise complementar');
});

it('shows only upcoming scheduled meetings in the follow-up meeting selector', function () {
    $this->travelTo('2026-11-14 14:00:00');
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião já concluída',
        'date' => '2026-11-10',
        'status' => 'completed',
    ]);
    MeetingActionItem::create([
        'meeting_id' => $meeting->id,
        'title' => 'Acompanhar próxima etapa',
        'description' => 'Revisar o andamento da entrega.',
        'status' => 'open',
    ]);

    foreach ([
        ['title' => 'Agenda de amanhã', 'date' => '2026-11-15', 'time' => '09:00', 'status' => 'scheduled'],
        ['title' => 'Agenda de hoje mais tarde', 'date' => '2026-11-14', 'time' => '12:00', 'status' => 'scheduled'],
        ['title' => 'Agenda de hoje sem horário', 'date' => '2026-11-14', 'time' => null, 'status' => 'scheduled'],
        ['title' => 'Agenda com data passada', 'date' => '2026-11-13', 'time' => '09:00', 'status' => 'scheduled'],
        ['title' => 'Agenda de hoje com horário passado', 'date' => '2026-11-14', 'time' => '10:00', 'status' => 'scheduled'],
        ['title' => 'Agenda futura concluída', 'date' => '2026-11-15', 'time' => '10:00', 'status' => 'completed'],
    ] as $agenda) {
        Meeting::create([
            'user_id' => $owner->id,
            'organizer_id' => $owner->id,
            'duration_minutes' => 30,
            ...$agenda,
        ]);
    }

    $this->actingAs($owner)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Agenda de amanhã')
        ->assertSee('Agenda de hoje mais tarde')
        ->assertSee('Agenda de hoje sem horário')
        ->assertDontSee('Agenda com data passada')
        ->assertSee('Agenda de hoje com horário passado')
        ->assertDontSee('Agenda futura concluída');

    $this->travelBack();
});
