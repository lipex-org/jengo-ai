<?php

declare(strict_types=1);

namespace Jengo\Ai\Exceptions;

class DriverException extends AiException
{
    public static function requestFailed(string $provider, string $error, int $statusCode = 0, ?array $rawBody = null): self
    {
        return new self(
            "AI request to [{$provider}] failed with status ({$statusCode}): {$error}",
            $statusCode,
            null,
            ['raw_body' => $rawBody]
        );
    }

    public static function unsupportedFeature(string $provider, string $feature): self
    {
        return new self("Feature [{$feature}] is not supported by driver [{$provider}].");
    }

    public static function unsupported(string $message): self
    {
        return new self($message);
    }

    public static function networkError(string $message): self
    {
        return new self($message, 0);
    }

    public static function invalidResponse(string $message): self
    {
        return new self($message, 500);
    }

    public static function apiError(string $message, int $statusCode = 500): self
    {
        return new self($message, $statusCode);
    }
}
