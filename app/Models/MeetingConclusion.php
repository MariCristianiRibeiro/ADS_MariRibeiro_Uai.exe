<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingConclusion extends Model
{
    protected $fillable = ['meeting_id', 'recorded_by', 'summary', 'outcome', 'concluded_at'];

    protected function casts(): array
    {
        return ['concluded_at' => 'datetime'];
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
