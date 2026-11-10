<?php

namespace App\Models;

use Database\Factories\MeetingPredictionEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingPredictionEvaluation extends Model
{
    /** @use HasFactory<MeetingPredictionEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_prediction_id', 'evaluation_version', 'actual_value', 'error', 'absolute_error', 'squared_error', 'actual_snapshot', 'observed_at',
    ];

    protected function casts(): array
    {
        return ['actual_snapshot' => 'array', 'observed_at' => 'datetime'];
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(MeetingPrediction::class, 'meeting_prediction_id');
    }
}
