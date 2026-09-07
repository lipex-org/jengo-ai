<?php

declare(strict_types=1);

namespace Jengo\Ai\Config;

use CodeIgniter\Config\BaseService;
use Jengo\Ai\AiClient;

class Services extends BaseService
{
    /**
     * Return a new or shared AiClient instance.
     */
    public static function ai(bool $getShared = true): AiClient
    {
        if ($getShared) {
            return static::getSharedInstance('ai');
        }

        return new AiClient();
    }
}
