<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingConclusion extends Model
{
    protected $fillable = [
        'meeting_id', 'recorded_by', 'summary', 'outcome', 'objective_achievement', 'decisions_count',
        'completed_topics_count', 'pending_topics', 'objective_changed', 'duration_sufficient', 'time_well_used',
        'replaceable_by_email', 'needs_follow_up', 'productivity_score', 'investment_return_score', 'concluded_at',
    ];

    protected function casts(): array
    {
        return [
            'concluded_at' => 'datetime', 'objective_changed' => 'boolean', 'duration_sufficient' => 'boolean',
            'time_well_used' => 'boolean', 'replaceable_by_email' => 'boolean', 'needs_follow_up' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
