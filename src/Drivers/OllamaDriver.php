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

class OllamaDriver extends AbstractDriver
{
    public function generate(AiRequest $request): ChatResponse
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'http://localhost:11434'), '/');
        $url = "{$baseUrl}/api/chat";
        $headers = [];
        $body = $this->buildRequestBody($request, stream: false);

        $response = $this->postJson($url, $headers, $body);

        $message = $response['message'] ?? [];
        $content = (string) ($message['content'] ?? '');

        $toolCalls = [];
        if (!empty($message['tool_calls'])) {
            foreach ($message['tool_calls'] as $tc) {
                $function = $tc['function'] ?? [];
                $id = uniqid('ollama_call_');
                $name = (string) ($function['name'] ?? '');
                $args = (array) ($function['arguments'] ?? []);
                $toolCalls[] = new ToolCall($id, $name, $args);
            }
        }

        $usage = new Usage(
            promptTokens: (int) ($response['prompt_eval_count'] ?? 0),
            completionTokens: (int) ($response['eval_count'] ?? 0),
            totalTokens: ((int) ($response['prompt_eval_count'] ?? 0)) + ((int) ($response['eval_count'] ?? 0))
        );

        $doneReason = (string) ($response['done_reason'] ?? 'stop');
        $finishReason = match ($doneReason) {
            'length' => FinishReason::LENGTH,
            default  => !empty($toolCalls) ? FinishReason::TOOL_CALLS : FinishReason::STOP,
        };

        return new ChatResponse(
            content: $content,
            usage: $usage,
            finishReason: $finishReason,
            toolCalls: $toolCalls,
            rawResponse: $response,
            model: (string) ($response['model'] ?? ($body['model'] ?? null))
        );
    }

    public function stream(AiRequest $request): StreamResponse
    {
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'http://localhost:11434'), '/');
        $url = "{$baseUrl}/api/chat";
        $headers = [];
        $body = $this->buildRequestBody($request, stream: true);

        $rawStream = $this->postStream($url, $headers, $body);

        $generator = (function () use ($rawStream): Generator {
            foreach ($rawStream as $line) {
                if ($line === '') {
                    continue;
                }
                $data = json_decode($line, true);
                if (!is_array($data)) {
                    continue;
                }

                $delta = $data['message']['content'] ?? '';
                if ($delta !== '') {
                    yield (string) $delta;
                }

                if (($data['done'] ?? false) === true) {
                    break;
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
        $baseUrl = rtrim((string) ($this->config['base_url'] ?? 'http://localhost:11434'), '/');
        $selectedModel = $model ?? (string) ($this->config['embedding_model'] ?? ($this->config['model'] ?? 'nomic-embed-text'));
        $url = "{$baseUrl}/api/embed";

        $body = [
            'model' => $selectedModel,
            'input' => count($texts) === 1 ? $texts[0] : array_values($texts),
        ];

        $response = $this->postJson($url, [], $body);

        $embeddings = [];
        foreach ($response['embeddings'] ?? [] as $emb) {
            $embeddings[] = (array) $emb;
        }

        return new EmbeddingResponse(
            embeddings: $embeddings,
            model: $selectedModel
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequestBody(AiRequest $request, bool $stream = false): array
    {
        $model = $request->getModel() ?? (string) ($this->config['model'] ?? 'llama3.2');

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

        $options = [];
        if ($request->getTemperature() !== null) {
            $options['temperature'] = $request->getTemperature();
        }
        if ($request->getMaxTokens() !== null) {
            $options['num_predict'] = $request->getMaxTokens();
        }
        if ($request->getTopP() !== null) {
            $options['top_p'] = $request->getTopP();
        }

        $body = [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => $stream,
        ];

        if (!empty($options)) {
            $body['options'] = $options;
        }

        if ($request->hasSchema()) {
            $body['format'] = $request->getSchema();
        }

        if ($request->hasTools()) {
            $body['tools'] = array_values(array_map(fn($tool) => $tool->toArray(), $request->getTools()));
        }

        return array_merge($body, $request->getOptions());
    }
}
