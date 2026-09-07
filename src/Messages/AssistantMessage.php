<?php

declare(strict_types=1);

namespace Jengo\Ai\Messages;

use Jengo\Ai\Enums\Role;

class AssistantMessage extends Message
{
    public function __construct(string $content = '', ?array $toolCalls = null, ?string $name = null)
    {
        parent::__construct(Role::ASSISTANT, $content, name: $name, toolCalls: $toolCalls);
    }
}
