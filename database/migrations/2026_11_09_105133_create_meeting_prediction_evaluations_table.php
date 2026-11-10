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
        Schema::create('meeting_prediction_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_prediction_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('evaluation_version');
            $table->decimal('actual_value', 14, 4);
            $table->decimal('error', 14, 4);
            $table->decimal('absolute_error', 14, 4);
            $table->decimal('squared_error', 14, 4);
            $table->json('actual_snapshot');
            $table->timestamp('observed_at');
            $table->timestamps();
            $table->unique(['meeting_prediction_id', 'evaluation_version']);
            $table->index(['meeting_prediction_id', 'observed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_prediction_evaluations');
    }
};
