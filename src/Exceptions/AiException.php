<?php

declare(strict_types=1);

namespace Jengo\Ai\Exceptions;

use RuntimeException;
use Throwable;

class AiException extends RuntimeException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        protected ?array $context = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): ?array
    {
        return $this->context;
    }
}
