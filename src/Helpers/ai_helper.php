<?php

declare(strict_types=1);

use Jengo\Ai\Ai;
use Jengo\Ai\AiClient;
use Jengo\Ai\AiRequest;
use Jengo\Ai\Config\Services;

if (!function_exists('ai')) {
    /**
     * Helper to initiate an AI prompt/chat request or retrieve the AiClient instance.
     *
     * @param string|array<int, mixed>|null $prompt
     */
    function ai(string|array|null $prompt = null): AiRequest|AiClient
    {
        if ($prompt === null) {
            return Services::ai();
        }

        if (is_array($prompt)) {
            return Ai::chat($prompt);
        }

        return Ai::prompt($prompt);
    }
}
