<?php

declare(strict_types=1);

namespace Jengo\Ai\Support;

use Jengo\Ai\Enums\Role;
use Jengo\Ai\Messages\Message;

class PromptTemplate
{
    public function __construct(
        protected string $template
    ) {
    }

    public static function make(string $template): self
    {
        return new self($template);
    }

    /**
     * Render the template with variable replacements ({var_name} or {{var_name}}).
     *
     * @param array<string, mixed> $variables
     */
    public function render(array $variables = []): string
    {
        $rendered = $this->template;

        foreach ($variables as $key => $val) {
            $stringVal = is_array($val) ? json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $val;
            $rendered = str_replace(["{{$key}}", "{{ {$key} }}", "{{{$key}}}"], $stringVal, $rendered);
        }

        return $rendered;
    }

    public function toMessage(array $variables = [], Role $role = Role::USER): Message
    {
        return new Message($role, $this->render($variables));
    }
}
