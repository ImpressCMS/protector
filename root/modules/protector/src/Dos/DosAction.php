<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

final class DosAction
{
    public const EXIT = 'exit';

    public const NONE = 'none';

    public const BAN_TEMPORARILY = 'biptime0';

    public const BAN_PERMANENTLY = 'bip';

    public const DENY_BY_HTACCESS = 'hta';

    public const SLEEP = 'sleep';

    private const ALL = [
        self::EXIT,
        self::NONE,
        self::BAN_TEMPORARILY,
        self::BAN_PERMANENTLY,
        self::DENY_BY_HTACCESS,
        self::SLEEP,
    ];

    public static function fromStored(string $value): string
    {
        return in_array($value, self::ALL, true) ? $value : self::EXIT;
    }
}
