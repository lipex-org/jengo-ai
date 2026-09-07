<?php

declare(strict_types=1);

namespace Jengo\Ai\Enums;

enum FinishReason: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case TOOL_CALLS = 'tool_calls';
    case CONTENT_FILTER = 'content_filter';
    case ERROR = 'error';
    case OTHER = 'other';
}
