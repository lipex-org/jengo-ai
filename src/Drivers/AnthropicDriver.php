<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Generator;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Enums\Role;
use Jengo\Ai\Exceptions\DriverException;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use Jengo\Ai\Support\ToolCall;

class AnthropicDriver extends AbstractDriver
{
    public function generate(AiRequest $request): ChatResponse
    {
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com/v1'), '/') . '/messages';
        $headers = $this->buildHeaders();
        $body = $this->buildRequestBody($request, stream: false);

        $response = $this->postJson($url, $headers, $body);

        $text = '';
        $toolCalls = [];

        foreach ($response['content'] ?? [] as $block) {
            $type = $block['type'] ?? '';
            if ($type === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif ($type === 'tool_use') {
                $id = (string) ($block['id'] ?? uniqid('toolu_'));
                $name = (string) ($block['name'] ?? '');
                $input = (array) ($block['input'] ?? []);
                $toolCalls[] = new ToolCall($id, $name, $input);
            }
        }

        $usageData = $response['usage'] ?? [];
        $usage = new Usage(
            promptTokens: (int) ($usageData['input_tokens'] ?? 0),
            completionTokens: (int) ($usageData['output_tokens'] ?? 0),
            totalTokens: ((int) ($usageData['input_tokens'] ?? 0)) + ((int) ($usageData['output_tokens'] ?? 0))
        );

        $stopReason = (string) ($response['stop_reason'] ?? 'end_turn');
        $finishReason = match ($stopReason) {
            'end_turn', 'stop_sequence' => FinishReason::STOP,
            'max_tokens'                => FinishReason::LENGTH,
            'tool_use'                  => FinishReason::TOOL_CALLS,
            default                     => FinishReason::STOP,
        };

        return new ChatResponse(
            content: $text,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            rawResponse: $response,
            model: (string) ($response['model'] ?? ($body['model'] ?? null)),
            id: (string) ($response['id'] ?? null)
        );
    }

    public function stream(AiRequest $request): StreamResponse
    {
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com/v1'), '/') . '/messages';
        $headers = $this->buildHeaders();
        $body = $this->buildRequestBody($request, stream: true);

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

                $type = $data['type'] ?? '';
                if ($type === 'content_block_delta') {
                    $delta = $data['delta'] ?? [];
                    if (($delta['type'] ?? '') === 'text_delta') {
                        yield (string) ($delta['text'] ?? '');
                    }
                }
            }
        })();

        return new StreamResponse(
            generator: $generator,
            model: (string) ($body['model'] ?? null)
        );
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResponse
    {
        throw DriverException::unsupported('Anthropic does not provide a native vector embeddings endpoint. Please configure an OpenAI, Gemini, or Ollama embedding provider.');
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $key = (string) ($this->config['key'] ?? '');
        return [
            "x-api-key: {$key}",
            'anthropic-version: 2023-06-01',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(AiRequest $request, bool $stream = false): array
    {
        $model = $request->getModel() ?? (string) ($this->config['model'] ?? 'claude-3-5-sonnet-20241022');
        $maxTokens = $request->getMaxTokens() ?? 4096;

        $body = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $this->formatMessages($request),
        ];

        if ($stream) {
            $body['stream'] = true;
        }

        if ($request->getSystemPrompt() !== null) {
            $body['system'] = $request->getSystemPrompt();
        }

        if ($request->getTemperature() !== null) {
            $body['temperature'] = $request->getTemperature();
        }

        if ($request->getTopP() !== null) {
            $body['top_p'] = $request->getTopP();
        }

        // Tools
        if ($request->hasTools()) {
            $body['tools'] = array_values(array_map(fn($tool) => [
                'name'         => $tool->getName(),
                'description'  => $tool->getDescription(),
                'input_schema' => $tool->getParametersSchema(),
            ], $request->getTools()));
        }

        return array_merge($body, $request->getOptions());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formatMessages(AiRequest $request): array
    {
        $messages = [];

        foreach ($request->getMessages() as $msg) {
            $role = $msg->getRole();
            $content = $msg->getContent();

            if ($role === Role::SYSTEM) {
                // If a system message is in messages list, merge into system prompt if needed
                continue;
            }

            if ($role === Role::TOOL) {
                $rawMsg = $msg->toArray();
                $messages[] = [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'         => 'tool_result',
                            'tool_use_id'  => $rawMsg['tool_call_id'] ?? '',
                            'content'      => is_array($content) ? json_encode($content) : (string) $content,
                        ],
                    ],
                ];
                continue;
            }

            if ($role === Role::ASSISTANT) {
                $rawMsg = $msg->toArray();
                $contentBlocks = [];

                if (!empty($content)) {
                    $contentBlocks[] = [
                        'type' => 'text',
                        'text' => is_array($content) ? json_encode($content) : (string) $content,
                    ];
                }

                if (!empty($rawMsg['tool_calls'])) {
                    foreach ($rawMsg['tool_calls'] as $tc) {
                        $id = (string) ($tc['id'] ?? uniqid('toolu_'));
                        $name = (string) ($tc['function']['name'] ?? $tc['name'] ?? '');
                        $argsRaw = $tc['function']['arguments'] ?? $tc['arguments'] ?? [];
                        $args = is_string($argsRaw) ? (json_decode($argsRaw, true) ?? []) : (array) $argsRaw;

                        $contentBlocks[] = [
                            'type'  => 'tool_use',
                            'id'    => $id,
                            'name'  => $name,
                            'input' => $args,
                        ];
                    }
                }

                $messages[] = [
                    'role'    => 'assistant',
                    'content' => !empty($contentBlocks) ? $contentBlocks : (is_array($content) ? json_encode($content) : (string) $content),
                ];
                continue;
            }

            $messages[] = [
                'role'    => 'user',
                'content' => $content,
            ];
        }

        return $messages;
    }
}
