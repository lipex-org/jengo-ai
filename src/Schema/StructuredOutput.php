<?php

declare(strict_types=1);

namespace Jengo\Ai\Schema;

use Jengo\Ai\Exceptions\SchemaValidationException;

class StructuredOutput
{
    /**
     * Parse and extract clean JSON payload from raw model text response.
     *
     * @throws SchemaValidationException
     */
    public static function extract(string $text, ?array $schema = null): array
    {
        $cleaned = trim($text);

        // Strip markdown code fences (e.g. ```json ... ```)
        if (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleaned);
            $cleaned = trim((string) $cleaned);
        }

        // Locate outermost JSON { ... } or [ ... ]
        if (!str_starts_with($cleaned, '{') && !str_starts_with($cleaned, '[')) {
            $firstBrace = strpos($cleaned, '{');
            $firstBracket = strpos($cleaned, '[');

            $start = false;
            if ($firstBrace !== false && ($firstBracket === false || $firstBrace < $firstBracket)) {
                $start = $firstBrace;
                $end = strrpos($cleaned, '}');
            } elseif ($firstBracket !== false) {
                $start = $firstBracket;
                $end = strrpos($cleaned, ']');
            }

            if ($start !== false && isset($end) && $end !== false && $end > $start) {
                $cleaned = substr($cleaned, $start, $end - $start + 1);
            }
        }

        $decoded = json_decode($cleaned, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            throw SchemaValidationException::invalidJson($text, json_last_error_msg());
        }

        if ($schema !== null && isset($schema['required']) && is_array($schema['required'])) {
            $missing = [];
            foreach ($schema['required'] as $field) {
                if (!array_key_exists($field, $decoded)) {
                    $missing[] = $field;
                }
            }

            if (!empty($missing)) {
                throw SchemaValidationException::missingFields($missing, $decoded);
            }
        }

        return $decoded;
    }
}
