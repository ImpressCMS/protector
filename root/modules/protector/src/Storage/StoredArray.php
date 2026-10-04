<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Storage;

final class StoredArray
{
    /** @return array<int|string, mixed>|null */
    public static function decode(string $payload): ?array
    {
        if ($payload === '') {
            return null;
        }

        $value = @unserialize($payload, ['allowed_classes' => false]);

        return is_array($value) ? $value : null;
    }

    /** @param array<int|string, mixed> $value */
    public static function encode(array $value): string
    {
        return serialize($value);
    }
}
