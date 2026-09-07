<?php

declare(strict_types=1);

namespace Jengo\Ai\Responses;

class Usage
{
    public function __construct(
        public int $promptTokens = 0,
        public int $completionTokens = 0,
        public int $totalTokens = 0,
        public ?float $estimatedCostUsd = null
    ) {
        if ($this->totalTokens === 0 && ($this->promptTokens > 0 || $this->completionTokens > 0)) {
            $this->totalTokens = $this->promptTokens + $this->completionTokens;
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            promptTokens: (int) ($data['prompt_tokens'] ?? $data['input_tokens'] ?? 0),
            completionTokens: (int) ($data['completion_tokens'] ?? $data['output_tokens'] ?? 0),
            totalTokens: (int) ($data['total_tokens'] ?? 0)
        );
    }

    public function toArray(): array
    {
        return [
            'prompt_tokens'      => $this->promptTokens,
            'completion_tokens'  => $this->completionTokens,
            'total_tokens'       => $this->totalTokens,
            'estimated_cost_usd' => $this->estimatedCostUsd,
        ];
    }
}
