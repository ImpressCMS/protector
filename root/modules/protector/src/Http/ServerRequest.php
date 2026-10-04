<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Http;

final class ServerRequest
{
    public static function rawClientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    public static function clientIp(): string
    {
        return (string) filter_var(self::rawClientIp(), FILTER_VALIDATE_IP);
    }

    public static function uri(): string
    {
        return TextSanitiser::sanitise($_SERVER['REQUEST_URI'] ?? '');
    }

    public static function userAgent(): string
    {
        return TextSanitiser::sanitise($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public static function rawUserAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public static function scriptName(): string
    {
        return (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    }
}
