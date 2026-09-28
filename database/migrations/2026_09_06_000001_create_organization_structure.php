<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directorates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->decimal('hourly_rate', 10, 2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('directorate_id')->constrained()->restrictOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['directorate_id', 'code']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('directorate_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('directorate_id')->constrained()->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->after('team_id')->constrained()->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->after('job_position_id')->constrained('users')->nullOnDelete();
            $table->index(['role', 'status']);
        });

        Schema::table('meetings', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('meetings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('directorate_id');
        });

        Schema::dropIfExists('projects');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('job_positions');
        Schema::dropIfExists('directorates');
    }
};
