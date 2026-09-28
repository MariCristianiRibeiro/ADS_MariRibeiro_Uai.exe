<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'status', 'role', 'hourly_rate', 'salary', 'directorate_id', 'team_id', 'job_position_id', 'manager_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'string',
            'role' => 'string',
            'hourly_rate' => 'decimal:2',
            'salary' => 'decimal:2',
        ];
    }

    public function isAdmin(): bool
    {
        return ($this->role ?? 'participant') === 'admin';
    }

    public function isHr(): bool
    {
        return ($this->role ?? 'participant') === 'hr';
    }

    public function participants()
    {
        return $this->hasMany(Participant::class);
    }

    public function meetings()
    {
        return $this->belongsToMany(Meeting::class, 'meeting_user')->withTimestamps();
    }

    public function directorate()
    {
        return $this->belongsTo(Directorate::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function jobPosition()
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function directReports()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function supervisors()
    {
        return $this->belongsToMany(User::class, 'user_hierarchy', 'subordinate_user_id', 'supervisor_user_id')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    public function subordinates()
    {
        return $this->belongsToMany(User::class, 'user_hierarchy', 'supervisor_user_id', 'subordinate_user_id')
            ->withPivot('relationship_type')
            ->withTimestamps();
    }

    public function hasSupervisor(User $supervisor): bool
    {
        return $this->supervisors()->whereKey($supervisor->id)->exists();
    }

    public function canViewCosts(): bool
    {
        return in_array($this->role, ['admin', 'executive', 'director', 'director_commercial', 'director_network', 'manager'], true);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['manager', 'director', 'director_commercial', 'director_network', 'executive', 'admin'], true);
    }
}
