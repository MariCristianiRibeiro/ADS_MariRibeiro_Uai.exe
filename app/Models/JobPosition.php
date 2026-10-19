<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobPosition extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'hourly_rate', 'description', 'active'];

    protected function casts(): array
    {
        return ['hourly_rate' => 'decimal:2', 'active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
