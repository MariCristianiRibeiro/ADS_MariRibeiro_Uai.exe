<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('meetings', function (Blueprint $table): void {
            $table->timestamp('schedule_advanced_at')->nullable();
            $table->text('reschedule_reason')->nullable();
        });

        Schema::table('meeting_conclusions', function (Blueprint $table): void {
            $table->text('schedule_change_reason')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_conclusions', function (Blueprint $table): void {
            $table->dropColumn('schedule_change_reason');
        });

        Schema::table('meetings', function (Blueprint $table): void {
            $table->dropColumn(['schedule_advanced_at', 'reschedule_reason']);
        });
    }
};
