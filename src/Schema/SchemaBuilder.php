<?php

declare(strict_types=1);

namespace Jengo\Ai\Schema;

class SchemaBuilder
{
    /**
     * Convert a simple PHP array definition into a compliant JSON Schema object.
     *
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    public static function fromArray(array $definition, string $title = 'StructuredOutput', bool $strict = true): array
    {
        // If already a valid JSON schema containing 'type' => 'object', return it
        if (isset($definition['type']) && isset($definition['properties'])) {
            if ($strict && !isset($definition['additionalProperties'])) {
                $definition['additionalProperties'] = false;
            }
            return $definition;
        }

        $properties = [];
        $required = [];

        foreach ($definition as $key => $typeDefinition) {
            $isNullable = false;

            if (is_string($typeDefinition)) {
                $types = explode('|', $typeDefinition);
                $cleanTypes = [];
                foreach ($types as $t) {
                    $t = trim(strtolower($t));
                    if ($t === 'nullable') {
                        $isNullable = true;
                    } else {
                        $cleanTypes[] = $t;
                    }
                }

                $primaryType = $cleanTypes[0] ?? 'string';
                $prop = static::resolveStringType($primaryType);

                if ($isNullable) {
                    $prop['type'] = is_array($prop['type']) ? array_merge($prop['type'], ['null']) : [$prop['type'], 'null'];
                }

                $properties[$key] = $prop;
                if (!$isNullable) {
                    $required[] = $key;
                }
            } elseif (is_array($typeDefinition)) {
                // Nested object
                $nested = static::fromArray($typeDefinition, ucfirst($key), $strict);
                $properties[$key] = $nested;
                $required[] = $key;
            }
        }

        $schema = [
            'name'                 => $title,
            'strict'               => $strict,
            'type'                 => 'object',
            'properties'           => $properties,
            'required'             => $required,
            'additionalProperties' => false,
        ];

        return $schema;
    }

    protected static function resolveStringType(string $type): array
    {
        return match ($type) {
            'int', 'integer' => ['type' => 'integer'],
            'float', 'double', 'numeric', 'number' => ['type' => 'number'],
            'bool', 'boolean' => ['type' => 'boolean'],
            'email' => ['type' => 'string', 'description' => 'A valid email address'],
            'url' => ['type' => 'string', 'description' => 'A valid URL'],
            'date', 'datetime' => ['type' => 'string', 'description' => 'Date/time string'],
            'array', 'list' => ['type' => 'array', 'items' => ['type' => 'string']],
            default => ['type' => 'string'],
        };
    }
}
