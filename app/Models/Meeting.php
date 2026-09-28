<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'project_id',
        'title',
        'date',
        'time',
        'duration_minutes',
        'location',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date',
        'time' => 'string',
        'duration_minutes' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Participant::class)->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_user')->withTimestamps();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
