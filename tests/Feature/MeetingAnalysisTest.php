<?php

use App\Models\Meeting;
use App\Models\MeetingAnalysis;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('stores a structured meeting analysis returned by the Gemini API', function () {
    config(['services.ai.providers.gemini.api_key' => 'test-api-key']);
    Http::fake([
        'generativelanguage.googleapis.com/v1beta/models/*:generateContent' => Http::response([
            'modelVersion' => 'gemini-3.8-flash',
            'candidates' => [[
                'content' => ['parts' => [[
                    'text' => json_encode([
                        'summary' => 'A equipe aprovou o cronograma.',
                        'findings' => ['Cronograma aprovado.'],
                        'recommendations' => [['title' => 'Preparar plano', 'detail' => 'Consolidar atividades.', 'justification' => 'Facilita acompanhamento.']],
                        'risks' => [], 'opportunities' => [], 'missing_information' => [], 'confidence' => 0.9,
                        'historical_basis' => [], 'suggested_next_steps' => [],
                    ], JSON_THROW_ON_ERROR),
                ]]],
            ]],
        ]),
    ]);

    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião de implantação',
        'date' => '2026-12-11',
        'duration_minutes' => 45,
        'status' => 'completed',
    ]);

    $this->actingAs($owner)->post(route('meetings.analyses.store', $meeting), [
        'transcript' => 'A equipe aprovou o cronograma de implantação para a próxima semana.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $analysis = MeetingAnalysis::query()->firstOrFail();

    expect($analysis->meeting->is($meeting))->toBeTrue()
        ->and($analysis->requester->is($owner))->toBeTrue()
        ->and($analysis->status)->toBe('completed')
        ->and($analysis->summary)->toBe('A equipe aprovou o cronograma.')
        ->and($analysis->decisions)->toBe(['Cronograma aprovado.'])
        ->and($analysis->action_items[0]['description'])->toContain('Preparar plano')
        ->and($analysis->provider)->toBe('gemini')
        ->and($analysis->model)->toBe('gemini-3.8-flash');

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'generateContent'));
});

it('requires an API key and edit access before creating an analysis', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião sem análise',
        'date' => '2026-12-12',
        'duration_minutes' => 30,
        'status' => 'scheduled',
    ]);

    config(['services.ai.providers.gemini.api_key' => null]);
    $this->actingAs($owner)->post(route('meetings.analyses.store', $meeting), [
        'analysis_type' => 'pre_meeting', 'analysis_notes' => '',
    ])->assertSessionHasErrors('analysis_notes');

    expect(MeetingAnalysis::query()->firstOrFail()->status)->toBe('failed');

    $viewer = createUserWithPermissions(['meetings.view']);
    $this->actingAs($viewer)->post(route('meetings.analyses.store', $meeting), [
        'analysis_type' => 'pre_meeting', 'analysis_notes' => '',
    ])->assertForbidden();
});

it('preserves a failed analysis without exposing provider errors to the user', function () {
    config(['services.ai.providers.gemini.api_key' => 'test-api-key']);
    Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'provider details']], 503)]);

    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião com indisponibilidade de IA',
        'date' => '2026-12-13',
        'duration_minutes' => 30,
        'status' => 'completed',
    ]);

    $this->actingAs($owner)->post(route('meetings.analyses.store', $meeting), [
        'analysis_type' => 'post_meeting', 'analysis_notes' => 'A equipe concluiu a revisão e validou o resultado final.',
    ])->assertRedirect()->assertSessionHasErrors('analysis_notes');

    expect(MeetingAnalysis::query()->firstOrFail()->status)->toBe('failed')
        ->and(session('errors')->first('analysis_notes'))->toContain('Tente novamente')
        ->and(session('errors')->first('analysis_notes'))->not->toContain('provider details');
});
