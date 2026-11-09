<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeetingParticipant extends Model
{
    protected $fillable = [
        'meeting_id',
        'user_id',
        'role',
        'invitation_status',
        'confirmation_status',
        'attendance_status',
        'participation_effective', 'relevant_contribution', 'contributed_to_decision', 'participation_necessary', 'self_rating',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function costSnapshot(): HasOne
    {
        return $this->hasOne(MeetingCostSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'participation_effective' => 'boolean', 'relevant_contribution' => 'boolean',
            'contributed_to_decision' => 'boolean', 'participation_necessary' => 'boolean', 'self_rating' => 'integer',
        ];
    }
}
