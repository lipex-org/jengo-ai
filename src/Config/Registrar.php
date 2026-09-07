<?php

declare(strict_types=1);

namespace Jengo\Ai\Config;

use Jengo\Ai\Installers\AiInstaller;

class Registrar
{
    public static function JengoBase(): array
    {
        return [
            'installers' => [
                AiInstaller::class,
            ],
        ];
    }
}
