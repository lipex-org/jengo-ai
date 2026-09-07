<?php

declare(strict_types=1);

namespace Jengo\Ai\Exceptions;

class RateLimitException extends AiException
{
    public function __construct(
        string $message = '',
        int $code = 429,
        ?\Throwable $previous = null,
        ?array $context = null,
        protected ?int $retryAfter = null
    ) {
        parent::__construct($message, $code, $previous, $context);
    }

    public static function exceeded(string $provider, ?int $retryAfterSeconds = null): self
    {
        $message = "Rate limit exceeded for AI provider [{$provider}].";
        if ($retryAfterSeconds !== null) {
            $message .= " Retry after {$retryAfterSeconds} seconds.";
        }

        return new self($message, 429, null, ['retry_after' => $retryAfterSeconds], $retryAfterSeconds);
    }

    public static function providerLimit(string $message, ?int $retryAfter = null): self
    {
        return new self($message, 429, null, ['retry_after' => $retryAfter], $retryAfter);
    }

    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
