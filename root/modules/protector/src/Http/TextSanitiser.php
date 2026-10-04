<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Http;

final class TextSanitiser
{
    public static function sanitise(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        return str_replace(['\\', "'", '"'], ['&#92;', '&#39;', '&#34;'], strip_tags($value));
    }
}
