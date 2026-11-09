<?php

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingAnalysis;
use App\Models\MeetingParticipant;
use App\Models\MeetingParticipantFeedback;
use Illuminate\Support\Facades\Http;

it('stores the structured meeting outcome and contextual participant observations', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $participantUser = createUserWithPermissions(['meetings.view']);
    $meeting = Meeting::create(['user_id' => $owner->id, 'organizer_id' => $owner->id, 'title' => 'Revisão trimestral', 'date' => '2026-11-09', 'duration_minutes' => 60, 'status' => 'completed']);
    $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'user_id' => $participantUser->id, 'attendance_status' => 'present']);

    $this->actingAs($owner)->put(route('meetings.conclusion.save', $meeting), [
        'summary' => 'Plano revisado.', 'outcome' => 'Cronograma aprovado.', 'objective_achievement' => 'partially',
        'decisions_count' => 2, 'completed_topics_count' => 3, 'pending_topics' => 'Aprovar orçamento',
        'objective_changed' => 0, 'duration_sufficient' => 1, 'time_well_used' => 1, 'replaceable_by_email' => 0,
        'needs_follow_up' => 1, 'productivity_score' => 4, 'investment_return_score' => 3,
        'participation' => [$participant->id => ['participation_effective' => 1, 'relevant_contribution' => 1, 'contributed_to_decision' => 0, 'participation_necessary' => 1, 'self_rating' => 4]],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($participantUser)->put(route('meetings.participant-feedback.save', $meeting), [
        'clarity_score' => 5, 'objectivity_score' => 4, 'organization_score' => 4, 'time_management_score' => 3,
        'participation_score' => 4, 'effectiveness_score' => 4, 'suggestion' => 'Enviar materiais antes.', 'comments' => 'Boa conversa.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($meeting->fresh()->conclusion->objective_achievement)->toBe('partially')
        ->and($meeting->fresh()->conclusion->investment_return_score)->toBe(3)
        ->and($participant->fresh()->participation_effective)->toBeTrue()
        ->and(MeetingParticipantFeedback::query()->where('meeting_id', $meeting->id)->count())->toBe(1);
});

it('preserves each action item change including completion, lateness, and reopening', function () {
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
