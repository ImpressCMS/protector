<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Dos;

enum DosAction: string
{
    case Exit = 'exit';
    case None = 'none';
    case BanTemporarily = 'biptime0';
    case BanPermanently = 'bip';
    case DenyByHtaccess = 'hta';
    case Sleep = 'sleep';

    public static function fromStored(string $value): self
    {
        return self::tryFrom($value) ?? self::Exit;
    }
}
