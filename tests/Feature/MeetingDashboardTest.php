<?php

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingConclusion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function meetingDashboardFixture(User $organizer, array $attributes = []): Meeting
{
    return Meeting::create(array_merge([
        'user_id' => $organizer->id,
        'organizer_id' => $organizer->id,
        'title' => 'Revisão de planejamento',
        'category' => 'Planejamento',
        'date' => '2026-11-04',
        'duration_minutes' => 60,
        'actual_duration_seconds' => 3600,
        'status' => 'completed',
        'recurrence_type' => 'single',
    ], $attributes));
}

it('shows actual meeting hours separately from collaborator hours and hides individual data without permission', function () {
    $organizer = createUserWithPermissions(['reports.view', 'reports.costs'], ['role' => 'executive']);
    $first = User::factory()->create(['hourly_rate' => 100]);
    $second = User::factory()->create(['hourly_rate' => 120]);
    $meeting = meetingDashboardFixture($organizer);
    $meeting->users()->attach($first->id, ['attendance_status' => 'present']);
    $meeting->users()->attach($second->id, ['attendance_status' => 'present']);
    MeetingConclusion::create(['meeting_id' => $meeting->id, 'decisions_count' => 2, 'productivity_score' => 4]);
    MeetingActionItem::create(['meeting_id' => $meeting->id, 'title' => 'Concluir plano', 'description' => 'Plano final', 'status' => 'completed', 'completed_at' => now()]);

    $this->actingAs($organizer)->get(route('reports.meetings.dashboard'))
        ->assertOk()
        ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['meeting_hours'] === 1.0
            && $metrics['collaborator_hours'] === 2.0
            && $metrics['actual_cost'] === 220.0
            && $metrics['decisions'] === 2)
        ->assertDontSee($first->name)
        ->assertDontSee($second->name);
});

it('applies date and category filters within the user visible meeting scope', function () {
    $organizer = createUserWithPermissions(['reports.view'], ['role' => 'participant']);
    meetingDashboardFixture($organizer, ['title' => 'Filtered result', 'category' => 'Planning']);
    meetingDashboardFixture($organizer, ['title' => 'Other category', 'category' => 'Review']);
    meetingDashboardFixture(User::factory()->create(['role' => 'participant']), ['title' => 'Outside scope', 'category' => 'Planning']);

    $this->actingAs($organizer)->get(route('reports.meetings.dashboard', [
        'date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'category' => 'Planning',
    ]))->assertOk()->assertViewHas('metrics', fn (array $metrics): bool => $metrics['meeting_count'] === 1);
});

it('exports aggregated monthly data only to users with export permission', function () {
    $organizer = createUserWithPermissions(['reports.view', 'reports.export'], ['role' => 'executive']);
    meetingDashboardFixture($organizer);

    $response = $this->actingAs($organizer)->get(route('reports.meetings.export', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->assertStreamed();
    expect($response->streamedContent())->toContain('Horas colaborador');

    $viewer = createUserWithPermissions(['reports.view'], ['role' => 'executive']);
    $this->actingAs($viewer)->get(route('reports.meetings.export'))->assertForbidden();
});

it('sends only deterministic filtered aggregates to the configured AI and presents the response', function () {
    config([
        'services.ai.provider' => 'gemini',
        'services.ai.model' => 'gemini-dashboard-test',
        'services.ai.providers.gemini.api_key' => 'test-key',
        'services.ai.providers.gemini.base_url' => 'https://generativelanguage.googleapis.com/v1beta',
    ]);
    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'summary' => 'Os indicadores apontam uso concentrado em reuniões recorrentes.', 'findings' => ['Há ações pendentes.'],
            'recommendations' => [['title' => 'Revisar pauta', 'detail' => 'Agrupe os assuntos.', 'justification' => 'Há concentração de reuniões.']],
            'risks' => [], 'opportunities' => ['Consolidar pautas.'], 'missing_information' => [], 'confidence' => 0.8,
            'historical_basis' => [], 'suggested_next_steps' => ['Acompanhar entregas.'],
        ], JSON_UNESCAPED_UNICODE)]]]]],
        'usageMetadata' => ['promptTokenCount' => 20, 'candidatesTokenCount' => 30],
    ])]);
    $organizer = createUserWithPermissions(['reports.view'], ['role' => 'executive']);
    meetingDashboardFixture($organizer);

    $this->actingAs($organizer)->post(route('reports.meetings.analyze'), [
        'date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'category' => 'Planejamento',
    ])->assertRedirect(route('reports.meetings.dashboard', [
        'date_from' => '2026-11-01', 'date_to' => '2026-11-30', 'department' => null, 'directorate' => null,
        'collaborator' => null, 'organizer' => null, 'category' => 'Planejamento', 'recurrence' => null, 'status' => null,
    ]));

    Http::assertSent(fn ($request): bool => str_contains($request->body(), 'custos_apurados')
        && str_contains($request->body(), 'periodo') && str_contains($request->body(), '2026-11-01'));
    $this->get(route('reports.meetings.dashboard', ['date_from' => '2026-11-01', 'date_to' => '2026-11-30']))
        ->assertOk()->assertSee('Os indicadores apontam uso concentrado em reuniões recorrentes.');
});
