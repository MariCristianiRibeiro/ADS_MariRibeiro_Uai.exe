<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingParticipantFeedback extends Model
{
    protected $table = 'meeting_participant_feedback';

    protected $fillable = [
        'meeting_id', 'user_id', 'clarity_score', 'objectivity_score', 'organization_score',
        'time_management_score', 'participation_score', 'effectiveness_score', 'suggestion', 'comments', 'submitted_at',
    ];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
