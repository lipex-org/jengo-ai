<?php

declare(strict_types=1);

namespace Jengo\Ai\Support;

use Closure;
use Jengo\Ai\Contracts\ToolInterface;

class Tool implements ToolInterface
{
    /** @var array<string, array{type: string, description: string, required: bool, enum?: array, default?: mixed}> */
    protected array $parameters = [];
    protected ?Closure $handler = null;

    public function __construct(
        protected string $name,
        protected string $description = ''
    ) {
    }

    public static function make(string $name, string $description = ''): self
    {
        return new self($name, $description);
    }

    public function parameter(
        string $name,
        string $type = 'string',
        string $description = '',
        bool $required = true,
        ?array $enum = null,
        mixed $default = null
    ): static {
        $param = [
            'type'        => $type,
            'description' => $description,
            'required'    => $required,
        ];

        if ($enum !== null) {
            $param['enum'] = $enum;
        }

        if ($default !== null) {
            $param['default'] = $default;
        }

        $this->parameters[$name] = $param;

        return $this;
    }

    public function handler(callable $handler): static
    {
        $this->handler = $handler(...);
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParameters(): array
    {
        return $this->getParametersSchema();
    }

    public function getParametersSchema(): array
    {
        $properties = [];
        $required = [];

        foreach ($this->parameters as $name => $meta) {
            $prop = [
                'type'        => $meta['type'],
                'description' => $meta['description'],
            ];

            if (isset($meta['enum'])) {
                $prop['enum'] = $meta['enum'];
            }

            if (isset($meta['default'])) {
                $prop['default'] = $meta['default'];
            }

            $properties[$name] = $prop;

            if (!empty($meta['required'])) {
                $required[] = $name;
            }
        }

        return [
            'type'       => 'object',
            'properties' => $properties,
            'required'   => $required,
        ];
    }

    public function execute(array $arguments): mixed
    {
        if ($this->handler === null) {
            throw new \RuntimeException("Tool [{$this->name}] has no execution handler defined.");
        }

        // If arguments are associative, filter out unknown parameters to prevent PHP 8 unknown named parameter errors
        $isAssociative = !empty($arguments) && array_keys($arguments) !== range(0, count($arguments) - 1);
        if ($isAssociative) {
            $ref = new \ReflectionFunction($this->handler);
            $hasVariadic = false;
            $allowed = [];
            foreach ($ref->getParameters() as $param) {
                if ($param->isVariadic()) {
                    $hasVariadic = true;
                    break;
                }
                $allowed[$param->getName()] = true;
            }

            if (!$hasVariadic) {
                $filtered = [];
                foreach ($arguments as $key => $val) {
                    if (isset($allowed[$key])) {
                        $filtered[$key] = $val;
                    }
                }
                $arguments = $filtered;
            }
        }

        return ($this->handler)(...$arguments);
    }

    public function toArray(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $this->name,
                'description' => $this->description,
                'parameters'  => $this->getParametersSchema(),
            ],
        ];
    }
}
