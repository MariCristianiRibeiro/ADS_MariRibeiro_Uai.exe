<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->text('objective')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->time('end_time')->nullable();
            $table->string('meeting_format')->nullable();
            $table->text('agenda')->nullable();
            $table->text('expected_outcome')->nullable();
            $table->string('priority')->default('normal');
            $table->string('recurrence_type')->default('single');
            $table->string('recurrence_frequency')->nullable();
            $table->unsignedSmallInteger('recurrence_interval')->nullable();
            $table->date('recurrence_ends_at')->nullable();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE meetings DROP CONSTRAINT IF EXISTS meetings_status_check');
        }

        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('status')->default('draft')->change();
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->index(['status', 'date']);
            $table->index(['organizer_id', 'date']);
        });
    }

    public function down(): void
    {
        DB::table('meetings')->where('status', 'draft')->update(['status' => 'scheduled']);

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropIndex(['status', 'date']);
            $table->dropIndex(['organizer_id', 'date']);
            $table->dropConstrainedForeignId('organizer_id');
            $table->dropColumn([
                'description',
                'category',
                'objective',
                'end_time',
                'meeting_format',
                'agenda',
                'expected_outcome',
                'priority',
                'recurrence_type',
                'recurrence_frequency',
                'recurrence_interval',
                'recurrence_ends_at',
            ]);
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->string('status')->default('scheduled')->change();
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE meetings ADD CONSTRAINT meetings_status_check CHECK (status IN ('scheduled', 'ongoing', 'completed', 'canceled'))");
        }
    }
};
