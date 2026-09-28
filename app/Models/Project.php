<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends \Illuminate\Database\Eloquent\Model
{
    use HasFactory;

    protected $fillable = ['team_id', 'manager_id', 'name', 'code', 'status'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class);
    }
}
