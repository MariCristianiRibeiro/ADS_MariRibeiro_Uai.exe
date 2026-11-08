<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'organizer_id',
        'title',
        'description',
        'category',
        'objective',
        'date',
        'time',
        'end_time',
        'duration_minutes',
        'actual_duration_seconds',
        'timer_started_at',
        'location',
        'meeting_format',
        'agenda',
        'expected_outcome',
        'priority',
        'recurrence_type',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_ends_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'recurrence_ends_at' => 'date',
        'time' => 'string',
        'duration_minutes' => 'integer',
        'actual_duration_seconds' => 'integer',
        'timer_started_at' => 'datetime',
        'recurrence_interval' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (Meeting $meeting): void {
            $meeting->history()->create([
                'actor_id' => Auth::id(),
                'event' => 'created',
                'changes' => ['status' => ['old' => null, 'new' => $meeting->status]],
            ]);
        });

        static::updated(function (Meeting $meeting): void {
            $changes = [];

            foreach ($meeting->getChanges() as $field => $newValue) {
                if (in_array($field, ['updated_at'], true)) {
                    continue;
                }

                $changes[$field] = [
                    'old' => $meeting->getOriginal($field),
                    'new' => $newValue,
                ];
            }

            if ($changes === []) {
                return;
            }

            $event = match (true) {
                isset($changes['status']) && $changes['status']['new'] === 'canceled' => 'canceled',
                isset($changes['date']) || isset($changes['time']) || isset($changes['end_time']) => 'rescheduled',
                isset($changes['status']) => 'status_changed',
                default => 'updated',
            };

            $meeting->history()->create([
                'actor_id' => Auth::id(),
                'event' => $event,
                'changes' => $changes,
            ]);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class)->withPivot('attendance_status')->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot(['id', 'role', 'invitation_status', 'confirmation_status', 'attendance_status'])
            ->withTimestamps();
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function meetingParticipants(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function costSnapshots(): HasManyThrough
    {
        return $this->hasManyThrough(
            MeetingCostSnapshot::class,
            MeetingParticipant::class,
            'meeting_id',
            'meeting_participant_id',
        );
    }

    public function conclusion(): HasOne
    {
        return $this->hasOne(MeetingConclusion::class);
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(MeetingDecision::class);
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(MeetingActionItem::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(MeetingHistory::class)->latest('created_at');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
