<?php

use App\Models\Directorate;
use App\Models\JobPosition;
use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingDecision;
use App\Models\MeetingParticipant;
use App\Models\Team;
use App\Models\User;
use App\Services\MeetingCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: Directorate, 1: Team, 2: JobPosition} */
function createMeetingOrganization(): array
{
    $directorate = Directorate::create(['name' => 'Operações', 'code' => 'OPS', 'active' => true]);
    $team = Team::create(['directorate_id' => $directorate->id, 'name' => 'Equipe Norte', 'code' => 'NORTE', 'active' => true]);
    $position = JobPosition::create(['name' => 'Analista', 'code' => 'ANALISTA', 'hourly_rate' => 125.50, 'active' => true]);

    return [$directorate, $team, $position];
}

it('stores a scheduled meeting, participant roles, and an immutable cost snapshot', function () {
    [$directorate, $team, $position] = createMeetingOrganization();
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create', 'meetings.edit']);
    $attendee = User::factory()->create([
        'job_position_id' => $position->id,
        'team_id' => $team->id,
        'directorate_id' => $directorate->id,
    ]);

    $this->actingAs($owner)->post(route('meetings.store'), [
        'title' => 'Planejamento trimestral',
        'description' => 'Revisão de indicadores.',
        'category' => 'Planejamento',
        'objective' => 'Definir metas.',
        'organizer_id' => $owner->id,
        'date' => '2026-12-01',
        'time' => '09:00',
        'end_time' => '10:30',
        'duration_minutes' => 90,
        'meeting_format' => 'hybrid',
        'agenda' => 'Indicadores e metas.',
        'expected_outcome' => 'Metas aprovadas.',
        'priority' => 'high',
        'recurrence_type' => 'recurring',
        'recurrence_frequency' => 'monthly',
        'recurrence_interval' => 1,
        'status' => 'scheduled',
        'participants' => [$attendee->id],
        'participant_roles' => [$attendee->id => 'decision_responsible'],
        'invitations' => [$attendee->id => 'sent'],
        'confirmations' => [$attendee->id => 'confirmed'],
        'attendance' => [$attendee->id => 'pending'],
    ])->assertRedirect(route('meetings.index'));

    $meeting = Meeting::query()->where('title', 'Planejamento trimestral')->firstOrFail();
    $meetingParticipant = $meeting->meetingParticipants()->with('costSnapshot')->firstOrFail();

    expect($meetingParticipant)->toBeInstanceOf(MeetingParticipant::class)
        ->and($meetingParticipant->user->is($attendee))->toBeTrue()
        ->and($meeting->organizer->is($owner))->toBeTrue()
        ->and($meetingParticipant->role)->toBe('decision_responsible')
        ->and($meetingParticipant->confirmation_status)->toBe('confirmed')
        ->and((float) $meetingParticipant->costSnapshot->hourly_rate)->toBe(125.5)
        ->and($meeting->costSnapshots()->count())->toBe(1)
        ->and($meetingParticipant->costSnapshot->team_id)->toBe($team->id)
        ->and(app(MeetingCostCalculator::class)->estimatedTotal($meeting))->toBe(188.25)
        ->and($meeting->history()->where('event', 'created')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('meetings.show', $meeting))
        ->assertOk()
        ->assertSee('Planejamento trimestral')
        ->assertSee('Decisões')
        ->assertSee('Encaminhamentos');

    $position->update(['hourly_rate' => 999]);

    expect((float) $meetingParticipant->fresh()->costSnapshot->hourly_rate)->toBe(125.5);
});

it('records schedule changes and cancellation without deleting the meeting', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create', 'meetings.edit', 'meetings.delete']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião reagendada',
        'date' => '2026-12-02',
        'time' => '09:00',
        'end_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);

    $this->actingAs($owner)->put(route('meetings.update', $meeting), [
        'title' => $meeting->title,
        'organizer_id' => $owner->id,
        'date' => '2026-12-03',
        'time' => '11:00',
        'end_time' => '12:00',
        'duration_minutes' => 60,
        'priority' => 'normal',
        'recurrence_type' => 'single',
        'status' => 'scheduled',
    ])->assertRedirect(route('meetings.index'));

    $meeting->refresh();

    expect($meeting->history()->where('event', 'rescheduled')->exists())->toBeTrue();

    $this->actingAs($owner)->patch(route('meetings.cancel', $meeting))->assertRedirect(route('meetings.index'));

    expect($meeting->fresh()->status)->toBe('canceled')
        ->and($meeting->history()->where('event', 'canceled')->exists())->toBeTrue();
});

it('tracks action dependencies, decisions, and conclusion against the meeting', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.edit']);
    $attendee = User::factory()->create();
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião de entrega',
        'date' => '2026-12-04',
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);
    $meeting->users()->attach($attendee->id, [
        'role' => 'participant',
        'invitation_status' => 'accepted',
        'confirmation_status' => 'confirmed',
        'attendance_status' => 'confirmed',
    ]);
    $dependency = $meeting->actionItems()->create([
        'title' => 'Levantar dados',
        'description' => 'Consolidar a base.',
    ]);

    $this->actingAs($owner)->post(route('meetings.action-items.store', $meeting), [
        'title' => 'Preparar apresentação',
        'description' => 'Montar material para diretoria.',
        'responsible_user_id' => $attendee->id,
        'due_date' => '2026-12-10',
        'priority' => 'high',
        'requires_follow_up' => '1',
        'dependencies' => [$dependency->id],
    ])->assertRedirect();

    $actionItem = MeetingActionItem::query()->where('title', 'Preparar apresentação')->firstOrFail();

    expect($actionItem->meeting->is($meeting))->toBeTrue()
        ->and($actionItem->responsible->is($attendee))->toBeTrue()
        ->and($actionItem->dependencies->modelKeys())->toBe([$dependency->id]);

    $this->actingAs($owner)->patch(route('meetings.action-items.update', [$meeting, $actionItem]), [
        'status' => 'completed',
        'delivered_result' => 'Apresentação enviada.',
        'due_date' => '2026-12-10',
        'requires_follow_up' => '1',
    ])->assertRedirect();

    $this->actingAs($owner)->post(route('meetings.decisions.store', $meeting), [
        'title' => 'Aprovar plano',
        'description' => 'O plano foi aprovado.',
        'decided_by' => $attendee->id,
    ])->assertRedirect();

    $this->actingAs($owner)->put(route('meetings.conclusion.save', $meeting), [
        'summary' => 'Reunião concluída.',
        'outcome' => 'Plano aprovado e distribuído.',
    ])->assertRedirect();

    expect($actionItem->fresh()->status)->toBe('completed')
        ->and($actionItem->fresh()->completed_at)->not->toBeNull()
        ->and($meeting->decisions()->first())->toBeInstanceOf(MeetingDecision::class)
        ->and($meeting->conclusion()->first()->outcome)->toBe('Plano aprovado e distribuído.');
});

it('rejects invalid meeting times and action item links outside the meeting', function () {
    $owner = createUserWithPermissions(['meetings.view', 'meetings.create', 'meetings.edit']);
    $meeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Reunião válida',
        'date' => '2026-12-04',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);
    $anotherMeeting = Meeting::create([
        'user_id' => $owner->id,
        'organizer_id' => $owner->id,
        'title' => 'Outra reunião',
        'date' => '2026-12-05',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ]);
    $foreignActionItem = $anotherMeeting->actionItems()->create([
        'title' => 'Item externo',
        'description' => 'Não deve ser dependência.',
    ]);

    $this->actingAs($owner)->post(route('meetings.store'), [
        'title' => 'Horário inválido',
        'organizer_id' => $owner->id,
        'date' => '2026-12-06',
        'time' => '11:00',
        'end_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'scheduled',
    ])->assertSessionHasErrors('end_time');

    $this->actingAs($owner)->post(route('meetings.action-items.store', $meeting), [
        'title' => 'Item inválido',
        'description' => 'Dependência de outra reunião.',
        'priority' => 'normal',
        'dependencies' => [$foreignActionItem->id],
    ])->assertSessionHasErrors('dependencies.0');
});
