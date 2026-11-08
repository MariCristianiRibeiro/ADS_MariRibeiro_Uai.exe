<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'requires_follow_up' => 'boolean',
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
}
