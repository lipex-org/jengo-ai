<?php

declare(strict_types=1);

namespace Jengo\Ai\Messages;

use Jengo\Ai\Contracts\MessageInterface;
use Jengo\Ai\Enums\Role;

class Message implements MessageInterface
{
    public function __construct(
        public Role $role,
        public string|array $content,
        public ?string $name = null,
        public ?array $toolCalls = null,
        public ?string $toolCallId = null
    ) {
    }

    public static function system(string $content): self
    {
        return new self(Role::SYSTEM, $content);
    }

    public static function user(string|array $content): self
    {
        return new self(Role::USER, $content);
    }

    public static function assistant(string $content, ?array $toolCalls = null): self
    {
        return new self(Role::ASSISTANT, $content, toolCalls: $toolCalls);
    }

    public static function tool(string|array $result, string $toolCallId, ?string $name = null): self
    {
        $content = is_array($result) ? json_encode($result, JSON_UNESCAPED_SLASHES) : (string) $result;
        return new self(Role::TOOL, $content, name: $name, toolCallId: $toolCallId);
    }

    public function getRole(): Role
    {
        return $this->role;
    }

    public function getContent(): string|array
    {
        return $this->content;
    }

    public function toArray(): array
    {
        $data = [
            'role'    => $this->role->value,
            'content' => $this->content,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        if ($this->toolCalls !== null) {
            $data['tool_calls'] = $this->toolCalls;
        }

        if ($this->toolCallId !== null) {
            $data['tool_call_id'] = $this->toolCallId;
        }

        return $data;
    }
}
