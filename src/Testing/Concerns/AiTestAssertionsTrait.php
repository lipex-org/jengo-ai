<?php

declare(strict_types=1);

namespace Jengo\Ai\Testing\Concerns;

use Jengo\Ai\Ai;
use Jengo\Ai\Testing\AiFake;

trait AiTestAssertionsTrait
{
    protected ?AiFake $aiFakeInstance = null;

    /**
     * Activate the AI fake double for the test.
     */
    protected function aiFake(mixed $responses = null): AiFake
    {
        $this->aiFakeInstance = Ai::fake($responses);
        return $this->aiFakeInstance;
    }

    /**
     * Get the active AiFake instance.
     */
    protected function getAiFake(): AiFake
    {
        if ($this->aiFakeInstance === null) {
            $this->aiFakeInstance = Ai::fake();
        }

        return $this->aiFakeInstance;
    }

    /**
     * Assert that a prompt containing specific text was sent to AI.
     */
    protected function assertAiPromptSent(string|callable $prompt): void
    {
        $this->getAiFake()->assertPromptSent($prompt);
    }

    /**
     * Assert that a prompt was NOT sent to AI.
     */
    protected function assertAiPromptNotSent(string|callable $prompt): void
    {
        $this->getAiFake()->assertPromptNotSent($prompt);
    }

    /**
     * Assert that a specific model was used in any AI request.
     */
    protected function assertAiModel(string $model): void
    {
        $this->getAiFake()->assertModel($model);
    }

    /**
     * Assert that a specific driver was used in any AI request.
     */
    protected function assertAiDriver(string $driver): void
    {
        $this->getAiFake()->assertDriver($driver);
    }

    /**
     * Assert that a tool was called by the AI.
     */
    protected function assertAiToolCalled(string $toolName, ?callable $callback = null): void
    {
        $this->getAiFake()->assertToolCalled($toolName, $callback);
    }

    /**
     * Assert that a tool was NOT called by the AI.
     */
    protected function assertAiToolNotCalled(string $toolName): void
    {
        $this->getAiFake()->assertToolNotCalled($toolName);
    }

    /**
     * Assert the total number of AI requests made.
     */
    protected function assertAiCount(int $expectedCount): void
    {
        $this->getAiFake()->assertCount($expectedCount);
    }

    /**
     * Assert that no AI requests were made.
     */
    protected function assertAiNothingSent(): void
    {
        $this->getAiFake()->assertNothingSent();
    }
}
