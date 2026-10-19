<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $duplicateName = DB::table('job_positions')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->value('name');

        if ($duplicateName !== null) {
            throw new RuntimeException("Não foi possível criar a restrição de cargos: o nome '{$duplicateName}' está duplicado.");
        }

        Schema::table('job_positions', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->unique('name');
        });

        Schema::create('access_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('resource');
            $table->string('action');
            $table->string('description');
            $table->timestamps();
            $table->unique(['resource', 'action']);
        });

        Schema::create('profile_permissions', function (Blueprint $table) {
            $table->foreignId('access_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('permission_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['access_profile_id', 'permission_id']);
        });

        Schema::create('user_profiles', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('access_profile_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['user_id', 'access_profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
        Schema::dropIfExists('profile_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('access_profiles');
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropUnique(['name']);
            $table->dropColumn('description');
        });
    }
};
