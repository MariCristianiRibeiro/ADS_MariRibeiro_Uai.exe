<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class MeetingAiService
{
    private const PROMPT_VERSION = 'meeting-insights-v2';

    /** @return array{result: array<string, mixed>, provider: string, model: string, response_id: ?string, usage: array<string, mixed>, prompt_version: string} */
    public function analyze(string $stage, array $context): array
    {
        $provider = strtolower((string) config('services.ai.provider', 'gemini'));
        $model = $this->configuredModel();
        $apiKey = (string) config("services.ai.providers.{$provider}.api_key", '');

        if ($apiKey === '') {
            throw new RuntimeException("O provedor de IA {$provider} não está configurado.");
        }

        $prompt = [
            'etapa' => $stage,
            'contexto_real' => $context,
            'campos_json_obrigatorios' => ['summary', 'findings', 'recommendations', 'risks', 'opportunities', 'missing_information', 'confidence', 'historical_basis', 'suggested_next_steps'],
            'recommendation_fields' => ['title', 'detail', 'justification'],
            'confidence_scale' => 'number between 0 and 1',
        ];

        $stageGoal = match ($stage) {
            'pre_meeting' => 'Avalie necessidade e alternativa assíncrona, participantes essenciais, duração, riscos, resultados possíveis, preparação e reuniões semelhantes. Contextualize os custos recebidos sem recalculá-los.',
            'during_meeting' => 'Gere resumo parcial, verifique cobertura da pauta com evidências nas anotações, indique assuntos sem informação registrada, prioridades restantes e alertas de tempo/desvio somente se os dados permitirem.',
            'post_meeting' => 'Avalie efetividade comparando objetivo e resultado, presença, condução observável nas notas, encaminhamentos, risco de não conclusão ou retrabalho, necessidade de novo encontro e feedback construtivo. Estime reuniões adicionais somente com histórico suficiente.',
            default => 'Analise os dados de reunião fornecidos.',
        };
        $systemPrompt = 'Você é um assistente de análise de reuniões. Responda em português e somente com JSON válido no formato solicitado. '.$stageGoal.' Use apenas dados fornecidos. Não invente falas, participantes, fatos, conclusões, padrões históricos ou valores financeiros. Os indicadores financeiros recebidos são calculados pelo sistema: apenas contextualize-os, nunca recalcule ou altere valores. Se faltarem evidências, declare isso em missing_information e reduza confidence. Não atribua declarações a pessoas sem evidência textual explícita. Não tome decisões nem crie encaminhamentos; apenas sugira.';
        if ($stage === 'dashboard') {
            $systemPrompt .= ' For dashboard analysis, explain aggregate indicators and selected period using only the supplied data. Highlight opportunities, areas with higher investment, recurrence patterns, pending item risks, and optimizations. Compare preserved predictions with actual outcomes only if both are supplied. Do not expose people or infer a weekly work capacity that is absent. Do not invent or cite numbers, percentages, counts, or financial values; describe trends qualitatively and point to backend indicators.';
        }
        if ($stage === 'follow_up') {
            $systemPrompt .= ' Compare a previsão inicial preservada com o resultado real e os encaminhamentos atualizados. Use os dados fornecidos sobre prazo, tempo de resolução, reaberturas, encontros adicionais, custo e horas acumulados. Não declare conclusão final enquanto houver demandas pendentes.';
        }
        $systemPrompt .= ' Trate análise post_meeting como preliminar enquanto houver demandas pendentes. Não use avaliações individuais isoladas para classificar desempenho pessoal.';
        $baseUrl = rtrim((string) config("services.ai.providers.{$provider}.base_url"), '/');

        try {
            $response = match ($provider) {
                'gemini' => Http::baseUrl($baseUrl)->withHeaders(['x-goog-api-key' => $apiKey])->acceptJson()->connectTimeout(10)->timeout(60)
                    ->post("/models/{$model}:generateContent", [
                        'systemInstruction' => ['parts' => [['text' => $systemPrompt]]],
                        'contents' => [['role' => 'user', 'parts' => [['text' => json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]]]],
                        'generationConfig' => ['responseMimeType' => 'application/json', 'responseSchema' => $this->geminiSchema(), 'maxOutputTokens' => 4096],
                    ]),
                'openai' => Http::baseUrl($baseUrl)->withToken($apiKey)->acceptJson()->connectTimeout(10)->timeout(60)
                    ->post('/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => json_encode($prompt, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)],
                        ],
                        'response_format' => ['type' => 'json_schema', 'json_schema' => ['name' => 'meeting_insights', 'strict' => true, 'schema' => $this->schema(true)]],
                        'max_completion_tokens' => 4096,
                    ]),
                default => throw new RuntimeException("Provedor de IA não suportado: {$provider}."),
            };

            if (! $response->successful()) {
                throw new RuntimeException("O provedor {$provider} retornou erro HTTP {$response->status()}.");
            }

            $output = $provider === 'gemini'
                ? $response->json('candidates.0.content.parts.0.text')
                : $response->json('choices.0.message.content');
            if (! is_string($output)) {
                throw new RuntimeException('O provedor de IA retornou uma resposta vazia.');
            }

            $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            $this->validateResult($result);
            if ($stage === 'dashboard' && $this->containsDashboardNumbers($result)) {
                throw new RuntimeException('A resposta da IA incluiu números em texto; os indicadores numéricos devem vir dos cálculos do sistema.');
            }

            $providerUsage = $provider === 'gemini' ? $response->json('usageMetadata', []) : $response->json('usage', []);
            if (! is_array($providerUsage)) {
                $providerUsage = [];
            }
            $usage = [
                'input_tokens' => $providerUsage['promptTokenCount'] ?? $providerUsage['prompt_tokens'] ?? null,
                'output_tokens' => $providerUsage['candidatesTokenCount'] ?? $providerUsage['completion_tokens'] ?? null,
                'total_tokens' => $providerUsage['totalTokenCount'] ?? $providerUsage['total_tokens'] ?? null,
                'cost' => $providerUsage['cost'] ?? $providerUsage['cost_usd'] ?? null,
                'cost_currency' => isset($providerUsage['cost_usd']) ? 'USD' : ($providerUsage['currency'] ?? null),
                'raw' => $providerUsage,
            ];

            return [
                'result' => $result,
                'provider' => $provider,
                'model' => (string) ($provider === 'gemini' ? $response->json('modelVersion', $model) : $response->json('model', $model)),
                'response_id' => $provider === 'gemini' ? $response->json('responseId') : $response->json('id'),
                'usage' => $usage,
                'prompt_version' => self::PROMPT_VERSION,
            ];
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Não foi possível concluir a análise de IA. Verifique a conexão e tente novamente.', previous: $exception);
        }
    }

    public function isConfigured(): bool
    {
        $provider = strtolower((string) config('services.ai.provider', 'gemini'));

        return filled(config("services.ai.providers.{$provider}.api_key"));
    }

    public function provider(): string
    {
        return strtolower((string) config('services.ai.provider', 'gemini'));
    }

    public function configuredModel(): string
    {
        return (string) (config('services.ai.model') ?: match ($this->provider()) {
            'openai' => 'gpt-4.1-mini',
            default => config('services.gemini.model', 'gemini-3.8-flash'),
        });
    }

    /** @return array<string, mixed> */
    private function schema(bool $lowercaseTypes = false): array
    {
        $schema = [
            'type' => 'OBJECT',
            'properties' => [
                'summary' => ['type' => 'STRING'],
                'findings' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'recommendations' => ['type' => 'ARRAY', 'items' => ['type' => 'OBJECT', 'properties' => [
                    'title' => ['type' => 'STRING'], 'detail' => ['type' => 'STRING'], 'justification' => ['type' => 'STRING'],
                ], 'required' => ['title', 'detail', 'justification'], 'additionalProperties' => false]],
                'risks' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'opportunities' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'missing_information' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'confidence' => ['type' => 'NUMBER'],
                'historical_basis' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
                'suggested_next_steps' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING']],
            ],
            'required' => ['summary', 'findings', 'recommendations', 'risks', 'opportunities', 'missing_information', 'confidence', 'historical_basis', 'suggested_next_steps'],
            'additionalProperties' => false,
        ];

        if ($lowercaseTypes) {
            array_walk_recursive($schema, function (&$value): void {
                if (is_string($value)) {
                    $value = strtolower($value);
                }
            });
        }

        return $schema;
    }

    /** @return array<string, mixed> */
    private function geminiSchema(): array
    {
        $schema = $this->schema();
        unset($schema['additionalProperties'], $schema['properties']['recommendations']['items']['additionalProperties']);

        return $schema;
    }

    /** @param array<string, mixed> $result */
    private function validateResult(array $result): void
    {
        foreach (['summary', 'findings', 'recommendations', 'risks', 'opportunities', 'missing_information', 'confidence', 'historical_basis', 'suggested_next_steps'] as $key) {
            if (! array_key_exists($key, $result)) {
                throw new RuntimeException('A resposta da IA não corresponde ao formato esperado.');
            }
        }
        if (array_diff(array_keys($result), ['summary', 'findings', 'recommendations', 'risks', 'opportunities', 'missing_information', 'confidence', 'historical_basis', 'suggested_next_steps']) !== []) {
            throw new RuntimeException('A resposta da IA contém campos não previstos no formato validado.');
        }

        foreach (['findings', 'risks', 'opportunities', 'missing_information', 'historical_basis', 'suggested_next_steps'] as $key) {
            if (! is_array($result[$key]) || collect($result[$key])->contains(fn ($item): bool => ! is_string($item))) {
                throw new RuntimeException('A resposta da IA contém dados estruturados inválidos.');
            }
        }

        if (! is_string($result['summary']) || ! is_numeric($result['confidence']) || $result['confidence'] < 0 || $result['confidence'] > 1 || ! is_array($result['recommendations'])) {
            throw new RuntimeException('A resposta da IA contém dados estruturados inválidos.');
        }

        foreach ($result['recommendations'] as $recommendation) {
            if (! is_array($recommendation) || ! is_string($recommendation['title'] ?? null) || ! is_string($recommendation['detail'] ?? null) || ! is_string($recommendation['justification'] ?? null)) {
                throw new RuntimeException('A resposta da IA contém recomendações inválidas.');
            }
        }

        $text = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (preg_match('/(?:R\s?\$|US\$|\bBRL\b|\bUSD\b|\breais\b)/iu', $text) === 1) {
            throw new RuntimeException('A resposta incluiu valores financeiros em texto; esses valores devem vir dos cálculos do sistema.');
        }
    }

    /** @param array<string, mixed> $result */
    private function containsDashboardNumbers(array $result): bool
    {
        unset($result['confidence']);
        $text = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return preg_match('/\d/u', $text) === 1;
    }
}
