<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

enum LogLevel: int
{
    case Basic = 1;
    case Dos = 16;
    case Injection = 32;
    case Traversal = 64;
    case Spam = 128;
}
