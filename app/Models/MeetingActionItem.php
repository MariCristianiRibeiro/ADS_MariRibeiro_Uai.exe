<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingActionItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'responsible_user_id',
        'directorate_id',
        'title',
        'description',
        'due_date',
        'priority',
        'status',
        'completed_at',
        'delivered_result',
        'requires_follow_up',
        'expected_result', 'delay_reason', 'needs_additional_meeting', 'follow_up_meeting_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'requires_follow_up' => 'boolean',
            'needs_additional_meeting' => 'boolean',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function directorate(): BelongsTo
    {
        return $this->belongsTo(Directorate::class);
    }

    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'meeting_action_item_dependencies', 'action_item_id', 'depends_on_action_item_id');
    }

    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'meeting_action_item_dependencies', 'depends_on_action_item_id', 'action_item_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(MeetingActionItemHistory::class, 'meeting_action_item_id')->latest('created_at');
    }
}
