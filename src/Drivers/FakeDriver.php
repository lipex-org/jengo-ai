<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Contracts\DriverInterface;
use Jengo\Ai\Responses\ChatResponse;
use Jengo\Ai\Responses\EmbeddingResponse;
use Jengo\Ai\Responses\StreamResponse;
use Jengo\Ai\Testing\AiFake;

class FakeDriver implements DriverInterface
{
    public function __construct(
        protected AiFake $fake
    ) {
    }

    public function generate(AiRequest $request): ChatResponse
    {
        return $this->fake->handleGenerate($request, 'fake');
    }

    public function stream(AiRequest $request): StreamResponse
    {
        return $this->fake->handleStream($request, 'fake');
    }

    public function embed(array $texts, ?string $model = null): EmbeddingResponse
    {
        return $this->fake->handleEmbed($texts, $model, 'fake');
    }

    public function getFake(): AiFake
    {
        return $this->fake;
    }
}
