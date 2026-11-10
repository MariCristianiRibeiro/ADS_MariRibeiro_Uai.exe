<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingRecommendationFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingRecommendationFeedback>
 */
class MeetingRecommendationFeedbackFactory extends Factory
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
            'user_id' => User::factory(),
            'recommendation_key' => 'reduce_duration',
            'recommendation_title' => 'Avalie a duração prevista',
            'decision' => 'accepted',
        ];
    }
}
