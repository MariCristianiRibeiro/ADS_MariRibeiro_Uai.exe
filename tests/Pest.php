<?php

use App\Models\AccessProfile;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function createAdminUser(array $attributes = []): User
{
    $user = User::factory()->create(array_merge(['role' => 'admin'], $attributes));
    $profile = AccessProfile::where('name', 'Administrador geral')->firstOrFail();
    $user->accessProfiles()->attach($profile);

    return $user;
}

/**
 * @param  array<int, string>  $permissionNames
 */
function createUserWithPermissions(array $permissionNames, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $profile = AccessProfile::create([
        'name' => 'Perfil de teste '.$user->id,
        'active' => true,
    ]);

    foreach ($permissionNames as $permissionName) {
        [$resource, $action] = explode('.', $permissionName, 2);
        $permission = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['resource' => $resource, 'action' => $action, 'description' => $permissionName],
        );
        $profile->permissions()->attach($permission);
    }

    $user->accessProfiles()->attach($profile);

    return $user;
}

/** @param array<string, mixed> $data */
function postMeetingAfterPreview(TestCase $test, User $user, array $data): TestResponse
{
    $data = array_merge([
        'organizer_id' => $user->id,
        'duration_minutes' => 60,
        'priority' => 'normal',
        'recurrence_type' => 'single',
        'status' => 'scheduled',
    ], $data);
    $preview = $test->actingAs($user)->post(route('meetings.preview'), $data);

    if (! $preview->isOk()) {
        return $preview;
    }

    return $test->actingAs($user)->post(route('meetings.store'), [
        'preflight_token' => $preview->viewData('preflightToken'),
    ]);
}
