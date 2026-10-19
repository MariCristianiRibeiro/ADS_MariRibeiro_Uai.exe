<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $fillable = ['name', 'resource', 'action', 'description'];

    public function profiles(): BelongsToMany
    {
        return $this->belongsToMany(AccessProfile::class, 'profile_permissions')->withTimestamps();
    }
}
