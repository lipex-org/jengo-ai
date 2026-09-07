<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Generator;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use Jengo\Ai\Support\ToolCall;

class OpenAiDriver extends AbstractDriver
{
    public function generate(AiRequest $request): ChatResponse
    {
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/chat/completions';
        $headers = $this->buildHeaders();
        $body = $this->buildRequestBody($request, stream: false);

        $response = $this->postJson($url, $headers, $body);

        $choice = $response['choices'][0] ?? [];
        $message = $choice['message'] ?? [];
        $content = (string) ($message['content'] ?? '');

        $toolCalls = [];
        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $id = (string) ($tc['id'] ?? uniqid('call_'));
                $function = $tc['function'] ?? [];
                $name = (string) ($function['name'] ?? '');
                $rawArgs = $function['arguments'] ?? '{}';
                $args = is_string($rawArgs) ? (json_decode($rawArgs, true) ?? []) : (array) $rawArgs;

                $toolCalls[] = new ToolCall($id, $name, $args);
            }
        }

        $usageData = $response['usage'] ?? [];
        $usage = new Usage(
            promptTokens: (int) ($usageData['prompt_tokens'] ?? 0),
            completionTokens: (int) ($usageData['completion_tokens'] ?? 0),
            totalTokens: (int) ($usageData['total_tokens'] ?? 0)
        );

        $finishReasonRaw = (string) ($choice['finish_reason'] ?? 'stop');
        $finishReason = match ($finishReasonRaw) {
            'stop'          => FinishReason::STOP,
            'length'        => FinishReason::LENGTH,
            'tool_calls'    => FinishReason::TOOL_CALLS,
            'content_filter'=> FinishReason::CONTENT_FILTER,
            default         => FinishReason::STOP,
        };

        return new ChatResponse(
            content: $content,
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
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/chat/completions';
        $headers = $this->buildHeaders();
        $body = $this->buildRequestBody($request, stream: true);

        $rawStream = $this->postStream($url, $headers, $body);

        $generator = (function () use ($rawStream): Generator {
            foreach ($rawStream as $line) {
                if (!str_starts_with($line, 'data:')) {
                    continue;
                }
                $payload = trim(substr($line, 5));
                if ($payload === '[DONE]' || $payload === '') {
                    continue;
                }

                $data = json_decode($payload, true);
                if (!is_array($data)) {
                    continue;
                }

                $delta = $data['choices'][0]['delta'] ?? [];
                if (isset($delta['content']) && $delta['content'] !== '') {
                    yield (string) $delta['content'];
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
        $url = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/') . '/embeddings';
        $headers = $this->buildHeaders();
        $selectedModel = $model ?? (string) ($this->config['embedding_model'] ?? 'text-embedding-3-small');

        $body = [
            'model' => $selectedModel,
            'input' => count($texts) === 1 ? $texts[0] : array_values($texts),
        ];

        $response = $this->postJson($url, $headers, $body);

        $embeddings = [];
        foreach ($response['data'] ?? [] as $item) {
            $embeddings[] = (array) ($item['embedding'] ?? []);
        }

        $usageData = $response['usage'] ?? [];
        $usage = new Usage(
            promptTokens: (int) ($usageData['prompt_tokens'] ?? 0),
            totalTokens: (int) ($usageData['total_tokens'] ?? 0)
        );

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $selectedModel,
            usage: $usage
        );
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $key = (string) ($this->config['key'] ?? '');
        $headers = [
            "Authorization: Bearer {$key}",
        ];

        if (!empty($this->config['organization'])) {
            $headers[] = "OpenAI-Organization: " . (string) $this->config['organization'];
        }

        return $headers;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(AiRequest $request, bool $stream = false): array
    {
        $model = $request->getModel() ?? (string) ($this->config['model'] ?? 'gpt-4o-mini');
        $messages = $this->formatMessages($request);

        $body = [
            'model'    => $model,
            'messages' => $messages,
        ];

        if ($stream) {
            $body['stream'] = true;
        }

        if ($request->getTemperature() !== null) {
            $body['temperature'] = $request->getTemperature();
        }

        if ($request->getMaxTokens() !== null) {
            $body['max_tokens'] = $request->getMaxTokens();
        }

        if ($request->getTopP() !== null) {
            $body['top_p'] = $request->getTopP();
        }

        // Tools
        if ($request->hasTools()) {
            $body['tools'] = array_map(fn($tool) => [
                'type'     => 'function',
                'function' => $tool->toArray(),
            ], $request->getTools());
            $body['tool_choice'] = 'auto';
        }

        // Structured Schema Output
        if ($request->hasSchema()) {
            $body['response_format'] = [
                'type'        => 'json_schema',
                'json_schema' => [
                    'name'   => 'response_schema',
                    'strict' => true,
                    'schema' => $request->getSchema(),
                ],
            ];
        }

        return array_merge($body, $request->getOptions());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function formatMessages(AiRequest $request): array
    {
        $messages = [];

        if ($request->getSystemPrompt() !== null) {
            $messages[] = [
                'role'    => 'system',
                'content' => $request->getSystemPrompt(),
            ];
        }

        foreach ($request->getMessages() as $msg) {
            $messages[] = $msg->toArray();
        }

        return $messages;
    }
}
