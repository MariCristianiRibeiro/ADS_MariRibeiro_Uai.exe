<?php

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
<?php

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingPredictionEvaluation;
use App\Services\MeetingPredictionService;

it('withholds statistical values when the historical sample is insufficient', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Planejamento de entrega',
        'category' => 'planning',
        'objective' => 'Definir próximos passos',
        'date' => now()->addDay()->toDateString(),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $predictions = app(MeetingPredictionService::class)->generateFor($meeting, $owner);

    expect($predictions->where('status', 'insufficient_history')->count())->toBe(5)
        ->and($predictions->firstWhere('prediction_type', 'planned_cost')->status)->toBe('deterministic')
        ->and($predictions->firstWhere('prediction_type', 'actual_duration_minutes')->predicted_value)->toBeNull();
});

it('validates forecasts against earlier meetings and preserves each actual evaluation', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    for ($index = 1; $index <= 15; $index++) {
        $date = now()->subDays(20 + $index);
        $historical = Meeting::create([
            'user_id' => $owner->id,
            'organizer_id' => $owner->id,
            'title' => 'Planejamento de entrega',
            'category' => 'planning',
            'objective' => 'Definir próximos passos',
            'date' => $date->toDateString(),
            'duration_minutes' => 60,
            'actual_duration_seconds' => 3600,
            'status' => 'completed',
        ]);
        MeetingActionItem::create([
            'meeting_id' => $historical->id,
            'title' => 'Concluir etapa '.$index,
            'description' => 'Entrega vinculada ao planejamento.',
            'due_date' => $date->copy()->addDays(5)->toDateString(),
            'status' => 'completed',
            'completed_at' => $date->copy()->addDays($index % 2 === 0 ? 4 : 7),
        ]);
    }
    $target = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Planejamento de entrega',
        'category' => 'planning',
        'objective' => 'Definir próximos passos',
        'date' => now()->addDay()->toDateString(),
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $predictions = app(MeetingPredictionService::class)->generateFor($target, $owner);
    $duration = $predictions->firstWhere('prediction_type', 'actual_duration_minutes');
    $onTime = $predictions->firstWhere('prediction_type', 'on_time_probability');

    expect($duration->status)->toBe('validated')
        ->and($duration->observations_count)->toBe(15)
        ->and($duration->validation_observations)->toBe(5)
        ->and((float) $duration->predicted_value)->toBe(60.0)
        ->and($onTime->status)->toBe('validated')
        ->and($onTime->predicted_value)->toBeGreaterThan(0)
        ->and($onTime->predicted_value)->toBeLessThan(1);

    $target->update(['status' => 'completed', 'actual_duration_seconds' => 5400]);
    app(MeetingPredictionService::class)->evaluatePending($target->id);
    $duration->refresh();
    expect($duration->actual_value)->toBe('90.0000')
        ->and($duration->error)->toBe('30.0000')
        ->and(MeetingPredictionEvaluation::query()->where('meeting_prediction_id', $duration->id)->count())->toBe(1)
        ->and($duration->evaluations()->first()->actual_snapshot['actual_duration_minutes'])->toBe(90);

    $target->update(['actual_duration_seconds' => 6000]);
    app(MeetingPredictionService::class)->evaluatePending($target->id);
    expect(MeetingPredictionEvaluation::query()->where('meeting_prediction_id', $duration->id)->count())->toBe(2)
        ->and($duration->refresh()->predicted_value)->toBe('60.0000');
});

it('records recommendation acceptance and rejection as separate historical feedback', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Revisão semanal',
        'category' => 'planning',
        'date' => now()->addDay()->toDateString(),
        'duration_minutes' => 90,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)->post(route('meetings.recommendations.feedback', $meeting), [
        'recommendation_key' => 'long_duration',
        'decision' => 'accepted',
        'rationale' => 'A pauta será dividida em blocos.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $this->actingAs($owner)->post(route('meetings.recommendations.feedback', $meeting), [
        'recommendation_key' => 'long_duration',
        'decision' => 'rejected',
        'rationale' => 'O grupo precisa resolver todos os itens juntos.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($meeting->recommendationFeedback()->count())->toBe(2)
        ->and($meeting->recommendationFeedback()->where('decision', 'accepted')->exists())->toBeTrue()
        ->and($meeting->recommendationFeedback()->where('decision', 'rejected')->exists())->toBeTrue();
});
