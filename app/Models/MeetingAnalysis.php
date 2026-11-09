<?php

namespace App\Models;

use Database\Factories\MeetingAnalysisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAnalysis extends Model
{
    /** @use HasFactory<MeetingAnalysisFactory> */
    use HasFactory;

    protected $fillable = [
        'meeting_id', 'requested_by', 'analysis_type', 'version', 'prompt_version', 'input_data', 'result', 'usage', 'provider_cost',
        'transcript', 'summary', 'decisions', 'action_items', 'provider', 'model', 'response_id', 'status', 'error_message', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['input_data' => 'array', 'result' => 'array', 'usage' => 'array', 'decisions' => 'array', 'action_items' => 'array', 'completed_at' => 'datetime'];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
