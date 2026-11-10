<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingPrediction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingPrediction>
 */
class MeetingPredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'generation' => 1,
            'prediction_type' => 'duration_minutes',
            'model_name' => 'rolling_median_v1',
            'model_version' => '1.0.0',
            'status' => 'insufficient_history',
            'predicted_at' => now(),
            'features' => ['category' => 'planning'],
            'predicted_value' => null,
            'unit' => 'minutes',
            'confidence' => null,
            'observations_count' => 0,
            'validation_observations' => 0,
            'validation_metrics' => null,
        ];
    }
}
