<?php

declare(strict_types=1);

namespace Jengo\Ai\Testing;

use Closure;
use Generator;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Contracts\DriverInterface;
use Jengo\Ai\Drivers\FakeDriver;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Responses\Usage;
use Jengo\Ai\Support\ToolCall;
use PHPUnit\Framework\Assert;

class AiFake
{
    /**
     * @var array<int, array{request: AiRequest, driver: string, response: mixed}>
     */
    protected array $recorded = [];

    /**
     * @var array<int, mixed>
     */
    protected array $responseQueue = [];

    /**
     * @var array<int, array<int, float>>
     */
    protected array $embeddingQueue = [];

    /**
     * @var array<int, array<int, string>>
     */
    protected array $streamQueue = [];

    protected mixed $defaultResponse = 'Fake AI response';

    /**
     * @param array<int|string, mixed>|string|null $responses
     */
    public function __construct(array|string|null $responses = null)
    {
        if (is_string($responses)) {
            $this->responseQueue[] = $responses;
        } elseif (is_array($responses)) {
            foreach ($responses as $response) {
                $this->responseQueue[] = $response;
            }
        }
    }

    /**
     * Create a FakeDriver wrapping this fake instance.
     */
    public function createDriver(): DriverInterface
    {
        return new FakeDriver($this);
    }

    /**
     * Push a response onto the response queue.
     */
    public function push(mixed $response): self
    {
        $this->responseQueue[] = $response;
        return $this;
    }

    /**
     * Push a response that instructs the client to invoke a tool call.
     *
     * @param array<string, mixed> $arguments
     */
    public function pushToolCall(string $toolName, array $arguments = [], ?string $id = null): self
    {
        $toolCall = new ToolCall($id ?? uniqid('call_'), $toolName, $arguments);
        $response = new ChatResponse(
            content: '',
            usage: new Usage(10, 10, 20),
            finishReason: FinishReason::TOOL_CALLS,
            toolCalls: [$toolCall]
        );

        $this->responseQueue[] = $response;
        return $this;
    }

    /**
     * Push chunks for streaming fake responses.
     *
     * @param array<int, string> $chunks
     */
    public function pushStream(array $chunks): self
    {
        $this->streamQueue[] = $chunks;
        return $this;
    }

    /**
     * Push an embedding vector for fake embedding requests.
     *
     * @param array<int, float> $embedding
     */
    public function pushEmbedding(array $embedding): self
    {
        $this->embeddingQueue[] = $embedding;
        return $this;
    }

    /**
     * Set a default fallback response when queue is exhausted.
     */
    public function defaultResponse(mixed $response): self
    {
        $this->defaultResponse = $response;
        return $this;
    }

    /**
     * Handle generation for FakeDriver.
     */
    public function handleGenerate(AiRequest $request, string $driverName = 'fake'): ChatResponse
    {
        $next = !empty($this->responseQueue)
            ? array_shift($this->responseQueue)
            : $this->defaultResponse;

        if ($next instanceof Closure) {
            $next = $next($request);
        }

        $chatResponse = match (true) {
            $next instanceof ChatResponse => $next,
            is_array($next)               => new ChatResponse(
                content: (string) json_encode($next, JSON_UNESCAPED_SLASHES),
                usage: new Usage(15, 25, 40),
                model: $request->getModel() ?? 'fake-model'
            ),
            default                       => new ChatResponse(
                content: (string) $next,
                usage: new Usage(10, 20, 30),
                model: $request->getModel() ?? 'fake-model'
            ),
        };

        $this->recorded[] = [
            'request'  => $request,
            'driver'   => $driverName,
            'response' => $chatResponse,
        ];

        return $chatResponse;
    }

    /**
     * Handle streaming for FakeDriver.
     */
    public function handleStream(AiRequest $request, string $driverName = 'fake'): StreamResponse
    {
        $chunks = !empty($this->streamQueue)
            ? array_shift($this->streamQueue)
            : ['Fake ', 'streaming ', 'AI ', 'response'];

        $generator = (function () use ($chunks): Generator {
            foreach ($chunks as $chunk) {
                yield $chunk;
            }
        })();

        $this->recorded[] = [
            'request'  => $request,
            'driver'   => $driverName,
            'response' => $chunks,
        ];

        return new StreamResponse(
            generator: $generator,
            model: $request->getModel() ?? 'fake-stream-model'
        );
    }

    /**
     * Handle embedding generation for FakeDriver.
     *
     * @param array<int, string> $texts
     */
    public function handleEmbed(array $texts, ?string $model = null, string $driverName = 'fake'): EmbeddingResponse
    {
        $embeddings = [];

        foreach ($texts as $text) {
            if (!empty($this->embeddingQueue)) {
                $embeddings[] = array_shift($this->embeddingQueue);
            } else {
                // Generate a deterministic fake 16-dimensional embedding vector
                $hash = crc32($text);
                $vector = [];
                for ($i = 0; $i < 16; $i++) {
                    $vector[] = sin($hash + $i);
                }
                $embeddings[] = $vector;
            }
        }

        $response = new EmbeddingResponse(
            embeddings: $embeddings,
            model: $model ?? 'fake-embedding-model',
            usage: new Usage(count($texts) * 5, 0, count($texts) * 5)
        );

        return $response;
    }

    /**
     * Get all recorded interactions.
     *
     * @return array<int, array{request: AiRequest, driver: string, response: mixed}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    /**
     * Clear all recorded interactions and queued responses.
     */
    public function reset(): self
    {
        $this->recorded = [];
        $this->responseQueue = [];
        $this->streamQueue = [];
        $this->embeddingQueue = [];
        $this->defaultResponse = 'Fake AI response';
        return $this;
    }

    // ==========================================
    // Assertions
    // ==========================================

    /**
     * Assert that a prompt containing specific text or matching a callback was sent.
     */
    public function assertPromptSent(string|callable $prompt): self
    {
        $found = false;

        foreach ($this->recorded as $item) {
            /** @var AiRequest $request */
            $request = $item['request'];
            $fullPrompt = $request->getFormattedPrompt();

            if (is_callable($prompt)) {
                if ($prompt($request, $fullPrompt)) {
                    $found = true;
                    break;
                }
            } elseif (str_contains($fullPrompt, $prompt)) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, is_string($prompt) ? "Failed asserting that prompt containing '{$prompt}' was sent to AI." : "Failed asserting that callback condition matched sent prompts.");
        return $this;
    }

    /**
     * Assert that a prompt containing specific text was NOT sent.
     */
    public function assertPromptNotSent(string|callable $prompt): self
    {
        $found = false;

        foreach ($this->recorded as $item) {
            /** @var AiRequest $request */
            $request = $item['request'];
            $fullPrompt = $request->getFormattedPrompt();

            if (is_callable($prompt)) {
                if ($prompt($request, $fullPrompt)) {
                    $found = true;
                    break;
                }
            } elseif (str_contains($fullPrompt, $prompt)) {
                $found = true;
                break;
            }
        }

        Assert::assertFalse($found, is_string($prompt) ? "Failed asserting that prompt containing '{$prompt}' was NOT sent to AI." : "Failed asserting that callback condition did not match sent prompts.");
        return $this;
    }

    /**
     * Assert that a specific model was used in any recorded request.
     */
    public function assertModel(string $model): self
    {
        $found = false;
        foreach ($this->recorded as $item) {
            /** @var AiRequest $request */
            $request = $item['request'];
            if ($request->getModel() === $model) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Failed asserting that model '{$model}' was used in any AI request.");
        return $this;
    }

    /**
     * Assert that a specific driver was used in any recorded request.
     */
    public function assertDriver(string $driver): self
    {
        $found = false;
        foreach ($this->recorded as $item) {
            /** @var AiRequest $request */
            $request = $item['request'];
            if ($request->getDriver() === $driver || $item['driver'] === $driver) {
                $found = true;
                break;
            }
        }

        Assert::assertTrue($found, "Failed asserting that driver '{$driver}' was used in any AI request.");
        return $this;
    }

    /**
     * Assert that a specific tool was attached or invoked during generation.
     */
    public function assertToolCalled(string $toolName, ?callable $callback = null): self
    {
        $found = false;

        foreach ($this->recorded as $item) {
            $response = $item['response'];
            if ($response instanceof ChatResponse && $response->hasToolCalls()) {
                foreach ($response->toolCalls() as $toolCall) {
                    if ($toolCall->name === $toolName) {
                        if ($callback === null || $callback($toolCall->arguments)) {
                            $found = true;
                            break 2;
                        }
                    }
                }
            }
        }

        Assert::assertTrue($found, "Failed asserting that tool '{$toolName}' was called by AI.");
        return $this;
    }

    /**
     * Assert that a tool was NOT called by AI.
     */
    public function assertToolNotCalled(string $toolName): self
    {
        $found = false;

        foreach ($this->recorded as $item) {
            $response = $item['response'];
            if ($response instanceof ChatResponse && $response->hasToolCalls()) {
                foreach ($response->toolCalls() as $toolCall) {
                    if ($toolCall->name === $toolName) {
                        $found = true;
                        break 2;
                    }
                }
            }
        }

        Assert::assertFalse($found, "Failed asserting that tool '{$toolName}' was NOT called by AI.");
        return $this;
    }

    /**
     * Assert total count of requests made.
     */
    public function assertCount(int $expectedCount): self
    {
        Assert::assertCount($expectedCount, $this->recorded, "Expected {$expectedCount} AI requests, but " . count($this->recorded) . " were recorded.");
        return $this;
    }

    /**
     * Assert that zero requests were made to AI.
     */
    public function assertNothingSent(): self
    {
        return $this->assertCount(0);
    }
}
