<?php

declare(strict_types=1);

namespace Jengo\Ai\Exceptions;

class SchemaValidationException extends AiException
{
    public static function invalidJson(string $rawResponse, ?string $parseError = null): self
    {
        return new self(
            "Failed to parse structured JSON output from model response: {$parseError}",
            422,
            null,
            ['raw_response' => $rawResponse]
        );
    }

    public static function missingFields(array $missingFields, array $payload = []): self
    {
        $fields = implode(', ', $missingFields);
        return new self(
            "Structured output validation failed. Missing required field(s): [{$fields}].",
            422,
            null,
            ['missing_fields' => $missingFields, 'payload' => $payload]
        );
    }
}
