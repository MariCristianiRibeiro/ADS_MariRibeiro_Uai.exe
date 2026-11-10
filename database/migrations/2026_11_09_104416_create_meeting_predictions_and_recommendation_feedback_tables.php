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
        Schema::create('meeting_predictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('generation')->default(1);
            $table->string('prediction_type');
            $table->string('model_name');
            $table->string('model_version');
            $table->string('status');
            $table->timestamp('predicted_at');
            $table->json('features');
            $table->decimal('predicted_value', 14, 4)->nullable();
            $table->string('unit');
            $table->decimal('confidence', 6, 4)->nullable();
            $table->unsignedInteger('observations_count')->default(0);
            $table->unsignedSmallInteger('validation_observations')->default(0);
            $table->json('validation_metrics')->nullable();
            $table->decimal('actual_value', 14, 4)->nullable();
            $table->decimal('error', 14, 4)->nullable();
            $table->decimal('absolute_error', 14, 4)->nullable();
            $table->decimal('squared_error', 14, 4)->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'prediction_type', 'generation']);
            $table->index(['model_name', 'model_version', 'prediction_type', 'evaluated_at'], 'meeting_predictions_quality_index');
        });

        Schema::create('meeting_recommendation_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_prediction_id')->nullable()->constrained('meeting_predictions')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('recommendation_key');
            $table->string('recommendation_title');
            $table->string('decision');
            $table->text('rationale')->nullable();
            $table->decimal('estimated_saving', 12, 2)->nullable();
            $table->decimal('realized_saving', 12, 2)->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();
            $table->index(['meeting_id', 'recommendation_key', 'created_at'], 'meeting_recommendation_feedback_history_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_recommendation_feedback');
        Schema::dropIfExists('meeting_predictions');
    }
};
