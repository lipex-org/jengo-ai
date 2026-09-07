<?php

declare(strict_types=1);

namespace Jengo\Ai\Contracts;

use Jengo\Ai\Enums\Role;

interface MessageInterface
{
    public function getRole(): Role;

    public function getContent(): string|array;

    public function toArray(): array;
}
