<?php

declare(strict_types=1);

namespace Jengo\Ai\Drivers;

use Jengo\Ai\AiRequest;
use Jengo\Ai\Responses\ChatResponse;

class DeepSeekDriver extends OpenAiDriver
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'base_url' => 'https://api.deepseek.com/v1',
            'model'    => 'deepseek-chat',
        ];

        parent::__construct(array_merge($defaultConfig, $config));
    }
}
