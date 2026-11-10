<?php

namespace App\Models;

use Database\Factories\MeetingRecommendationFeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingRecommendationFeedback extends Model
{
    /** @use HasFactory<MeetingRecommendationFeedbackFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id', 'meeting_prediction_id', 'user_id', 'recommendation_key', 'recommendation_title', 'decision',
        'rationale', 'estimated_saving', 'realized_saving', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return ['estimated_saving' => 'decimal:2', 'realized_saving' => 'decimal:2', 'evaluated_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(MeetingPrediction::class, 'meeting_prediction_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
