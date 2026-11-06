<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionSeeder::class)->run();

        $now = now();
        $adminProfileId = $this->profileId('Administrador geral', 'Acesso total inicial para administradores existentes', $now);
        $permissionIds = DB::table('permissions')->pluck('id');

        foreach ($permissionIds as $permissionId) {
            DB::table('profile_permissions')->insertOrIgnore([
                'access_profile_id' => $adminProfileId,
                'permission_id' => $permissionId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (DB::table('users')->where('role', 'admin')->pluck('id') as $userId) {
            DB::table('user_profiles')->insertOrIgnore([
                'user_id' => $userId,
                'access_profile_id' => $adminProfileId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hrUserIds = DB::table('users')->where('role', 'hr')->pluck('id');

        if ($hrUserIds->isNotEmpty()) {
            $hrProfileId = $this->profileId('Gestão de pessoas e estrutura', 'Acesso legado convertido para permissões de perfil', $now);
            $hrPermissionIds = DB::table('permissions')
                ->whereIn('name', [
                    'users.administer',
                    'access_profiles.view',
                    'access_profiles.create',
                    'access_profiles.edit',
                    'access_profiles.administer',
                    'job_positions.view',
                    'job_positions.create',
                    'job_positions.edit',
                    'job_positions.deactivate',
                    'teams.view',
                    'teams.create',
                    'teams.manage',
                ])
                ->pluck('id');

            foreach ($hrPermissionIds as $permissionId) {
                DB::table('profile_permissions')->insertOrIgnore([
                    'access_profile_id' => $hrProfileId,
                    'permission_id' => $permissionId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($hrUserIds as $userId) {
                DB::table('user_profiles')->insertOrIgnore([
                    'user_id' => $userId,
                    'access_profile_id' => $hrProfileId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep the converted profiles and assignments because they may have been customized after migration.
    }

    private function profileId(string $name, string $description, Carbon $now): int
    {
        $profileId = DB::table('access_profiles')->where('name', $name)->value('id');

        if ($profileId !== null) {
            return (int) $profileId;
        }

        return (int) DB::table('access_profiles')->insertGetId([
            'name' => $name,
            'description' => $description,
            'active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
