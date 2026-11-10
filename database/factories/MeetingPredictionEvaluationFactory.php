<?php

namespace Database\Factories;

use App\Models\MeetingPrediction;
use App\Models\MeetingPredictionEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingPredictionEvaluation>
 */
class MeetingPredictionEvaluationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_prediction_id' => MeetingPrediction::factory(),
            'evaluation_version' => 1,
            'actual_value' => 45,
            'error' => -15,
            'absolute_error' => 15,
            'squared_error' => 225,
            'actual_snapshot' => ['status' => 'completed'],
            'observed_at' => now(),
        ];
    }
}
