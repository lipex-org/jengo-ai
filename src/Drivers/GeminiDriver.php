<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Generator;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Enums\Role;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use Jengo\Ai\Support\ToolCall;

class GeminiDriver extends AbstractDriver
{
    public function generate(AiRequest $request): ChatResponse
    {
        $model = $request->getModel() ?? (string) ($this->config['model'] ?? 'gemini-2.0-flash');
        $key = (string) ($this->config['key'] ?? '');
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = "{$baseUrl}/models/{$model}:generateContent?key={$key}";

        $headers = [];
        $body = $this->buildRequestBody($request);

        $response = $this->postJson($url, $headers, $body);

        $candidate = $response['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $text = '';
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $text .= (string) $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                $id = uniqid('gemini_call_');
                $name = (string) ($fc['name'] ?? '');
                $args = (array) ($fc['args'] ?? []);
                $toolCalls[] = new ToolCall($id, $name, $args);
            }
        }

        $usageData = $response['usageMetadata'] ?? [];
        $usage = new Usage(
            promptTokens: (int) ($usageData['promptTokenCount'] ?? 0),
            completionTokens: (int) ($usageData['candidatesTokenCount'] ?? 0),
            totalTokens: (int) ($usageData['totalTokenCount'] ?? 0)
        );

        $finishReasonRaw = (string) ($candidate['finishReason'] ?? 'STOP');
        $finishReason = match ($finishReasonRaw) {
            'STOP'          => FinishReason::STOP,
            'MAX_TOKENS'    => FinishReason::LENGTH,
            'SAFETY'        => FinishReason::CONTENT_FILTER,
            default         => !empty($toolCalls) ? FinishReason::TOOL_CALLS : FinishReason::STOP,
        };

        return new ChatResponse(
            content: $text,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            rawResponse: $response,
            model: $model
        );
    }

    public function stream(AiRequest $request): StreamResponse
    {
        $model = $request->getModel() ?? (string) ($this->config['model'] ?? 'gemini-2.0-flash');
        $key = (string) ($this->config['key'] ?? '');
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = "{$baseUrl}/models/{$model}:streamGenerateContent?alt=sse&key={$key}";

        $headers = [];
        $body = $this->buildRequestBody($request);

        $rawStream = $this->postStream($url, $headers, $body);

        $generator = (function () use ($rawStream): Generator {
            foreach ($rawStream as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '' || $payload === '[DONE]') {
                    continue;
                }

                $data = json_decode($payload, true);
                if (!is_array($data)) {
                    continue;
                }

                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text']) && $part['text'] !== '') {
                        yield (string) $part['text'];
                    }
                }
            }
        })();

        return new StreamResponse(
            generator: $generator,
            model: $model
        );
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResponse
    {
        $selectedModel = $model ?? (string) ($this->config['embedding_model'] ?? 'text-embedding-004');
        $key = (string) ($this->config['key'] ?? '');
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $embeddings = [];
        if (count($texts) === 1) {
            $url = "{$baseUrl}/models/{$selectedModel}:embedContent?key={$key}";
            $body = [
                'content' => [
                    'parts' => [
                        ['text' => $texts[0]],
                    ],
                ],
            ];
            $response = $this->postJson($url, [], $body);
            $embeddings[] = (array) ($response['embedding']['values'] ?? []);
        } else {
            $url = "{$baseUrl}/models/{$selectedModel}:batchEmbedContents?key={$key}";
            $requests = [];
            foreach ($texts as $text) {
                $requests[] = [
                    'model'   => "models/{$selectedModel}",
                    'content' => [
                        'parts' => [
                            ['text' => $text],
                        ],
                    ],
                ];
            }
            $response = $this->postJson($url, [], ['requests' => $requests]);
            foreach ($response['embeddings'] ?? [] as $emb) {
                $embeddings[] = (array) ($emb['values'] ?? []);
            }
        }

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $selectedModel
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(AiRequest $request): array
    {
        $body = [
            'contents' => $this->formatContents($request),
        ];

        if ($request->getSystemPrompt() !== null) {
            $body['system_instruction'] = [
                'parts' => [
                    ['text' => $request->getSystemPrompt()],
                ],
            ];
        }

        $generationConfig = [];
        if ($request->getTemperature() !== null) {
            $generationConfig['temperature'] = $request->getTemperature();
        }
        if ($request->getMaxTokens() !== null) {
            $generationConfig['maxOutputTokens'] = $request->getMaxTokens();
        }
        if ($request->getTopP() !== null) {
            $generationConfig['topP'] = $request->getTopP();
        }

        if ($request->hasSchema()) {
            $generationConfig['responseMimeType'] = 'application/json';
            $generationConfig['responseSchema'] = $request->getSchema();
        }

        if (!empty($generationConfig)) {
            $body['generationConfig'] = $generationConfig;
        }

        // Tools
        if ($request->hasTools()) {
            $declarations = [];
            foreach ($request->getTools() as $tool) {
                $declarations[] = [
                    'name'        => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'parameters'  => $tool->getParametersSchema(),
                ];
            }
            $body['tools'] = [
                ['functionDeclarations' => $declarations],
            ];
        }

        return array_merge($body, $request->getOptions());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formatContents(AiRequest $request): array
    {
        $contents = [];

        foreach ($request->getMessages() as $msg) {
            $role = $msg->getRole();
            $content = $msg->getContent();

            if ($role === Role::SYSTEM) {
                continue;
            }

            if ($role === Role::TOOL) {
                $rawMsg = $msg->toArray();
                $contents[] = [
                    'role'  => 'user',
                    'parts' => [
                        [
                            'functionResponse' => [
                                'name'     => $rawMsg['name'] ?? 'tool_result',
                                'response' => [
                                    'name'    => $rawMsg['name'] ?? 'tool_result',
                                    'content' => $content,
                                ],
                            ],
                        ],
                    ],
                ];
                continue;
            }

            $geminiRole = $role === Role::ASSISTANT ? 'model' : 'user';
            $contents[] = [
                'role'  => $geminiRole,
                'parts' => [
                    ['text' => is_array($content) ? json_encode($content) : (string) $content],
                ],
            ];
        }

        return $contents;
    }
}
