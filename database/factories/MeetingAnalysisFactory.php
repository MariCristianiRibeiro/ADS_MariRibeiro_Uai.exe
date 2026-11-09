<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingAnalysis>
 */
class MeetingAnalysisFactory extends Factory
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
            'requested_by' => User::factory(),
            'transcript' => fake()->paragraphs(3, true),
            'summary' => fake()->paragraph(),
            'decisions' => [fake()->sentence()],
            'action_items' => [],
            'provider' => 'gemini',
            'model' => 'gemini-3.8-flash',
            'status' => 'completed',
            'completed_at' => now(),
        ];
    }
}
