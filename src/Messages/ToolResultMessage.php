<?php

declare(strict_types=1);

namespace Jengo\Ai\Messages;

use Jengo\Ai\Enums\Role;

class ToolResultMessage extends Message
{
    public function __construct(string|array $result, string $toolCallId, ?string $name = null)
    {
        $content = is_array($result) ? json_encode($result, JSON_UNESCAPED_SLASHES) : (string) $result;
        parent::__construct(Role::TOOL, $content, name: $name, toolCallId: $toolCallId);
    }
}
