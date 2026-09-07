<?php

declare(strict_types=1);

namespace Jengo\Ai\Support;

use Jengo\Ai\Contracts\ToolInterface;

class ToolCall
{
    public function __construct(
        public string $id,
        public string $name,
        public array $arguments = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        $args = $data['function']['arguments'] ?? $data['arguments'] ?? [];
        if (is_string($args)) {
            $decoded = json_decode($args, true);
            $args = is_array($decoded) ? $decoded : [];
        }

        return new self(
            id: (string) ($data['id'] ?? uniqid('call_')),
            name: (string) ($data['function']['name'] ?? $data['name'] ?? ''),
            arguments: $args
        );
    }

    public function execute(mixed $handlerOrTool): mixed
    {
        if ($handlerOrTool instanceof ToolInterface) {
            return $handlerOrTool->execute($this->arguments);
        }

        if (is_callable($handlerOrTool)) {
            return call_user_func_array($handlerOrTool, $this->arguments);
        }

        throw new \InvalidArgumentException("Cannot execute tool [{$this->name}]: handler must be callable or ToolInterface.");
    }

    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'type'     => 'function',
            'function' => [
                'name'      => $this->name,
                'arguments' => json_encode($this->arguments, JSON_UNESCAPED_SLASHES),
            ],
        ];
    }
}
