<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class MeetingTranscriptAnalyzer
{
    /** @return array{summary: string, decisions: array<int, string>, action_items: array<int, array{description: string, responsible_hint: string, deadline_hint: string, priority: string}>, model: string, response_id: string|null} */
    public function analyze(string $transcript): array
    {
        $apiKey = config('services.openai.api_key');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            throw new RuntimeException('A integração ChatGPT não está configurada.');
        }

        $model = (string) config('services.openai.model', 'gpt-6-luna');
        $response = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
            ->withToken($apiKey)
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(90)
            ->post('/responses', [
                'model' => $model,
                'store' => false,
                'instructions' => 'Analise a transcrição de uma reunião em português. Extraia somente informações explícitas; não invente decisões, responsáveis, prazos ou prioridades. Responda no formato estruturado solicitado.',
                'input' => $transcript,
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'meeting_analysis',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'summary' => ['type' => 'string'],
                                'decisions' => ['type' => 'array', 'items' => ['type' => 'string']],
                                'action_items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'description' => ['type' => 'string'],
                                            'responsible_hint' => ['type' => 'string'],
                                            'deadline_hint' => ['type' => 'string'],
                                            'priority' => ['type' => 'string', 'enum' => ['low', 'normal', 'high', 'urgent']],
                                        ],
                                        'required' => ['description', 'responsible_hint', 'deadline_hint', 'priority'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['summary', 'decisions', 'action_items'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ]);

        if (! $response->successful() || $response->json('status') !== 'completed') {
            throw new RuntimeException('O serviço de análise não conseguiu concluir a solicitação.');
        }

        $outputText = collect($response->json('output', []))
            ->where('type', 'message')
            ->flatMap(fn (array $message): array => $message['content'] ?? [])
            ->first(fn (array $content): bool => ($content['type'] ?? null) === 'output_text')['text'] ?? null;

        if (! is_string($outputText)) {
            throw new RuntimeException('O serviço de análise retornou uma resposta vazia.');
        }

        $result = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);

        return [
            'summary' => $result['summary'],
            'decisions' => $result['decisions'],
            'action_items' => $result['action_items'],
            'model' => $response->json('model', $model),
            'response_id' => $response->json('id'),
        ];
    }
}
