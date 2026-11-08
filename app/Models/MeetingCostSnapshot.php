<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingCostSnapshot extends Model
{
    protected $fillable = [
        'meeting_participant_id',
        'job_position_id',
        'team_id',
        'directorate_id',
        'hourly_rate',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'decimal:2',
            'captured_at' => 'datetime',
        ];
    }

    public function meetingParticipant(): BelongsTo
    {
        return $this->belongsTo(MeetingParticipant::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }
}
