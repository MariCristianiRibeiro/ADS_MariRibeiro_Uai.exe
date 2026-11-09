<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingActionItemHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['meeting_action_item_id', 'actor_id', 'event', 'changes', 'created_at'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(MeetingActionItem::class, 'meeting_action_item_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
