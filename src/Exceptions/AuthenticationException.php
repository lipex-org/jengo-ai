<?php

declare(strict_types=1);

namespace Jengo\Ai\Exceptions;

class AuthenticationException extends AiException
{
    public static function invalidApiKey(string $provider): self
    {
        return new self("Invalid or missing API key for AI provider [{$provider}]. Please check your .env or Config/Ai.php.");
    }

    public static function invalidKey(string $message): self
    {
        return new self($message, 401);
    }
}
