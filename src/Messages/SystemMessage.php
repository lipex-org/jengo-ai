<?php

declare(strict_types=1);

namespace Jengo\Ai\Messages;

use Jengo\Ai\Enums\Role;

class SystemMessage extends Message
{
    public function __construct(string $content, ?string $name = null)
    {
        parent::__construct(Role::SYSTEM, $content, name: $name);
    }
}
