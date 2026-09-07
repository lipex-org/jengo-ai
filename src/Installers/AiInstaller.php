<?php

declare(strict_types=1);

namespace Jengo\Ai\Installers;

use CodeIgniter\CLI\CLI;
use Jengo\Base\Installers\Contracts\AbstractInstaller;

class AiInstaller extends AbstractInstaller
{
    public static function name(): string
    {
        return 'ai';
    }

    public static function description(): string
    {
        return 'Install AI multi-provider SDK support and publish configuration';
    }

    public static function reasonForSkipping(): string
    {
        return 'AI configuration already published in app/Config/Ai.php.';
    }

    public function shouldRun(): bool
    {
        return !file_exists(APPPATH . 'Config/Ai.php');
    }

    public function install(): void
    {
        $this->addRun();

        $dest = APPPATH . 'Config/Ai.php';
        if (file_exists($dest)) {
            CLI::write('Config/Ai.php already exists, skipping.', 'yellow');
            return;
        }

        $source = __DIR__ . '/../Config/Ai.php';
        $content = (string) file_get_contents($source);
        $content = str_replace(
            "namespace Jengo\\Ai\\Config;\n\nuse CodeIgniter\\Config\\BaseConfig;",
            "namespace Config;\n\nuse Jengo\\Ai\\Config\\Ai as BaseAi;",
            $content
        );
        $content = str_replace(
            "class Ai extends BaseConfig",
            "class Ai extends BaseAi",
            $content
        );

        $this->writeFile($dest, $content);
        CLI::write('Published Config/Ai.php successfully.', 'green');
    }
}
