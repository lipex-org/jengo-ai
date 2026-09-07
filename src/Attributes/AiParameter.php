<?php

declare(strict_types=1);

namespace Jengo\Ai\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class AiParameter
{
    public function __construct(
        public string $description = '',
        public bool $required = true,
        public ?array $enum = null,
        public ?string $type = null
    ) {
    }
}
