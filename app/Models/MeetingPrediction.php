<?php

namespace App\Models;

use Database\Factories\MeetingPredictionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingPrediction extends Model
{
    /** @use HasFactory<MeetingPredictionFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id', 'requested_by', 'generation', 'prediction_type', 'model_name', 'model_version', 'status',
        'predicted_at', 'features', 'predicted_value', 'unit', 'confidence', 'observations_count', 'validation_observations',
        'validation_metrics', 'actual_value', 'error', 'absolute_error', 'squared_error', 'evaluated_at',
    ];

    protected function casts(): array
    {
        return ['predicted_at' => 'datetime', 'evaluated_at' => 'datetime', 'features' => 'array', 'validation_metrics' => 'array'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(MeetingPredictionEvaluation::class)->orderBy('evaluation_version');
    }
}
