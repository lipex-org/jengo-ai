<?php

declare(strict_types=1);

namespace Jengo\Ai\Responses;

use Closure;
use CodeIgniter\HTTP\ResponseInterface as CiResponseInterface;
use Generator;
use IteratorAggregate;
use Traversable;

class StreamResponse implements IteratorAggregate
{
    protected ?string $accumulatedText = null;

    /**
     * @param Generator<int, string> $generator
     */
    public function __construct(
        protected Generator $generator,
        public ?string $model = null,
        public ?Usage $usage = null
    ) {
    }

    public function getIterator(): Traversable
    {
        return $this->generator;
    }

    /**
     * Consume the entire stream and return the full accumulated text.
     */
    public function text(): string
    {
        if ($this->accumulatedText !== null) {
            return $this->accumulatedText;
        }

        $full = '';
        foreach ($this->generator as $chunk) {
            $full .= $chunk;
        }

        $this->accumulatedText = $full;
        return $this->accumulatedText;
    }

    /**
     * Iterate through stream chunks with an optional callback.
     *
     * @param callable(string $chunk, string $accumulated): void $callback
     */
    public function each(callable $callback): string
    {
        $accumulated = '';
        foreach ($this->generator as $chunk) {
            $accumulated .= $chunk;
            $callback($chunk, $accumulated);
        }

        $this->accumulatedText = $accumulated;
        return $accumulated;
    }

    /**
     * Return a streaming CodeIgniter 4 Response configured for Server-Sent Events (SSE).
     */
    public function toSseResponse(): CiResponseInterface
    {
        /** @var CiResponseInterface $response */
        $response = service('response');

        $response->setHeader('Content-Type', 'text/event-stream; charset=UTF-8')
            ->setHeader('Cache-Control', 'no-cache, no-transform')
            ->setHeader('Connection', 'keep-alive')
            ->setHeader('X-Accel-Buffering', 'no');

        // Output stream with direct flush
        if (function_exists('ob_end_flush')) {
            @ob_end_flush();
        }

        $accumulated = '';
        foreach ($this->generator as $chunk) {
            $accumulated .= $chunk;
            $payload = json_encode(['chunk' => $chunk, 'accumulated' => $accumulated], JSON_UNESCAPED_SLASHES);
            echo "data: {$payload}\n\n";
            @ob_flush();
            @flush();
        }

        echo "data: [DONE]\n\n";
        @ob_flush();
        @flush();

        return $response->setBody('');
    }
}
