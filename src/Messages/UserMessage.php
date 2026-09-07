<?php

declare(strict_types=1);

namespace Jengo\Ai\Messages;

use Jengo\Ai\Enums\Role;

class UserMessage extends Message
{
    public function __construct(string|array $content, ?string $name = null)
    {
        parent::__construct(Role::USER, $content, name: $name);
    }
}
