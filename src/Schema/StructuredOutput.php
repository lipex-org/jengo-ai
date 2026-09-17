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

        // 1. Extract markdown code fences if present anywhere in text
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $cleaned, $matches)) {
            $cleaned = trim($matches[1]);
        } elseif (str_starts_with($cleaned, '```')) {
            $cleaned = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $cleaned);
            $cleaned = trim((string) $cleaned);
        }

        // 2. Locate outermost JSON { ... } or [ ... ]
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

        // 3. Fallback: sanitize trailing commas produced by LLMs (e.g. {"a": 1,})
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            $sanitized = preg_replace('/,\s*([}\]])/', '$1', $cleaned);
            if (is_string($sanitized)) {
                $decoded = json_decode($sanitized, true);
            }
        }

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
