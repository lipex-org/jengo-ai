<?php

declare(strict_types=1);

namespace Jengo\Ai\Contracts;

interface ToolInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * Get the JSON Schema for the tool parameters.
     *
     * @return array<string, mixed>
     */
    public function getParametersSchema(): array;

    /**
     * Execute the tool with parsed arguments.
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments): mixed;
}
