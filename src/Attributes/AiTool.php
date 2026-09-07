<?php

declare(strict_types=1);

namespace Jengo\Ai\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class AiTool
{
    public function __construct(
        public string $description,
        public ?string $name = null
    ) {
    }
}
