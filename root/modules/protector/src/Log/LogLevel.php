<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

final class LogLevel
{
    public const BASIC = 1;

    public const DOS = 16;

    public const INJECTION = 32;

    public const TRAVERSAL = 64;

    public const SPAM = 128;
}
