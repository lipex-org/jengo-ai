<?php

declare(strict_types=1);

namespace Jengo\Ai\Contracts;

use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Responses\Usage;

interface ResponseInterface
{
    /**
     * Get the generated text content.
     */
    public function text(): string;

    /**
     * Get token and cost usage statistics.
     */
    public function usage(): Usage;

    /**
     * Get the reason why generation completed.
     */
    public function finishReason(): FinishReason;

    /**
     * Check if the model triggered any tool calls.
     */
    public function hasToolCalls(): bool;

    /**
     * Get the list of tool calls requested by the model.
     *
     * @return array<\Jengo\Ai\Support\ToolCall>
     */
    public function toolCalls(): array;

    /**
     * Get the raw vendor provider response payload.
     *
     * @return array<string, mixed>
     */
    public function raw(): array;
}
