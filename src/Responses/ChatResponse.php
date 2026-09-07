<?php

declare(strict_types=1);

namespace Jengo\Ai\Responses;

use Jengo\Ai\Contracts\ResponseInterface;
use Jengo\Ai\Enums\FinishReason;
use Jengo\Ai\Exceptions\SchemaValidationException;
use Jengo\Ai\Support\ToolCall;
use JsonSerializable;
use Stringable;

class ChatResponse implements ResponseInterface, JsonSerializable, Stringable
{
    /**
     * @param array<int, ToolCall> $toolCalls
     * @param array<string, mixed> $rawResponse
     */
    public function __construct(
        protected string $content = '',
        public Usage $usage = new Usage(),
        public FinishReason $finishReason = FinishReason::STOP,
        protected array $toolCalls = [],
        protected array $rawResponse = [],
        public ?string $model = null,
        public ?string $id = null
    ) {
    }

    public function text(): string
    {
        return $this->content;
    }

    public function usage(): Usage
    {
        return $this->usage;
    }

    public function finishReason(): FinishReason
    {
        return $this->finishReason;
    }

    public function hasToolCalls(): bool
    {
        return !empty($this->toolCalls);
    }

    /**
     * @return array<int, ToolCall>
     */
    public function toolCalls(): array
    {
        return $this->toolCalls;
    }

    public function raw(): array
    {
        return $this->rawResponse;
    }

    /**
     * Decode structured output JSON into a PHP associative array.
     *
     * @throws SchemaValidationException
     */
    public function asArray(): array
    {
        $text = trim($this->content);

        // Strip markdown ```json ... ``` blocks if present
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text);
            $text = trim((string) $text);
        }

        $decoded = json_decode($text, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw SchemaValidationException::invalidJson($this->content, json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Decode structured output JSON into a PHP stdClass object.
     */
    public function asObject(): object
    {
        return (object) $this->asArray();
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function jsonSerialize(): array
    {
        return [
            'id'            => $this->id,
            'model'         => $this->model,
            'content'       => $this->content,
            'finish_reason' => $this->finishReason->value,
            'usage'         => $this->usage->toArray(),
            'tool_calls'    => array_map(fn(ToolCall $tc) => $tc->toArray(), $this->toolCalls),
        ];
    }
}
