<?php

namespace App\Services;

class MeetingTranscriptAnalyzer
{
    public function __construct(private MeetingAiService $aiService) {}

    /** @return array{summary: string, decisions: array<int, string>, action_items: array<int, array{description: string, responsible_hint: string, deadline_hint: string, priority: string}>, model: string, response_id: ?string} */
    public function analyze(string $transcript): array
    {
        $response = $this->aiService->analyze('post_meeting', ['anotacoes_manuais' => $transcript]);

        return [
            'summary' => $response['result']['summary'],
            'decisions' => $response['result']['findings'],
            'action_items' => [],
            'model' => $response['model'],
            'response_id' => $response['response_id'],
        ];
    }
}
