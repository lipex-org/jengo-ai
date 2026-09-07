<?php

declare(strict_types=1);

namespace Jengo\Ai\Contracts;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;

interface DriverInterface
{
    /**
     * Generate a synchronous completion from the AI provider.
     */
    public function generate(AiRequest $request): ChatResponse;

    /**
     * Generate a real-time streaming response from the AI provider.
     */
    public function stream(AiRequest $request): StreamResponse;

    /**
     * Generate vector embeddings for the provided texts.
     *
     * @param array<int, string> $texts
     */
    public function embed(array $texts, ?string $model = null): EmbeddingResponse;
}
