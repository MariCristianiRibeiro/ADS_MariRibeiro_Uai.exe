<?php

namespace Database\Factories;

use App\Models\AccessProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'status' => 'active',
            'role' => 'participant',
            'hourly_rate' => null,
            'salary' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $permissionNames = [
                'participants.view', 'participants.create', 'participants.edit', 'participants.delete',
                'meetings.view', 'meetings.create', 'meetings.edit', 'meetings.delete', 'meetings.finish',
                'projects.view', 'projects.create', 'reports.view', 'access_profiles.create', 'job_positions.view',
            ];
            $permissionIds = collect($permissionNames)->map(function (string $name): int {
                [$resource, $action] = explode('.', $name, 2);
                $permission = Permission::firstOrCreate(
                    ['resource' => $resource, 'action' => $action],
                    ['name' => $name, 'description' => str($action)->headline().' '.str($resource)->headline()],
                );

                return $permission->id;
            });
            $profile = AccessProfile::firstOrCreate(['name' => 'Pest baseline access'], ['active' => true]);
            $profile->permissions()->syncWithoutDetaching($permissionIds);
            $user->accessProfiles()->syncWithoutDetaching([$profile->id]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
