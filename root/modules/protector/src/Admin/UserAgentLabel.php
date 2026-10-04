<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

final class UserAgentLabel
{
    public static function shorten(string $agent): string
    {
        if (preg_match('/MSIE\s+([0-9.]+)/', $agent, $version)) {
            return "IE {$version[1]}";
        }

        if (stristr($agent, 'Gecko') !== false) {
            return strrchr($agent, ' ') ?: $agent;
        }

        $firstSpace = strpos($agent, ' ');

        return $firstSpace === false ? $agent : substr($agent, 0, $firstSpace);
    }
}
