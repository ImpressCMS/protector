<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Policy;

final class ViolationPolicy
{
    /**
     * @readonly
     */
    private int $flags;
    private const SANITIZE = 1;

    private const EXIT = 2;

    private const BAN_TEMPORARILY = 4;

    private const BAN_PERMANENTLY = 8;

    public function __construct(int $flags)
    {
        $this->flags = $flags;
    }

    public function sanitizes(): bool
    {
        return (bool) ($this->flags & self::SANITIZE);
    }

    public function exits(): bool
    {
        return (bool) ($this->flags & self::EXIT);
    }

    public function bansTemporarily(): bool
    {
        return (bool) ($this->flags & self::BAN_TEMPORARILY);
    }

    public function bansPermanently(): bool
    {
        return (bool) ($this->flags & self::BAN_PERMANENTLY);
    }
}
