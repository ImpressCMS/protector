<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

final class IpMatcher
{
    /** @param array<int|string, mixed> $entries */
    public function find(array $entries, string $ip): ?IpMatch
    {
        foreach ($entries as $pattern => $info) {
            $pattern = (string) $pattern;

            if ($pattern === '' || $pattern === '0') {
                continue;
            }

            if ($this->matches($pattern, $ip)) {
                return new IpMatch($pattern, $info);
            }
        }

        return null;
    }

    private function matches(string $pattern, string $ip): bool
    {
        $last = substr($pattern, -1);

        if ($last === '.') {
            return substr($ip, 0, strlen($pattern)) === $pattern;
        }

        if (ctype_digit($last)) {
            return $ip === $pattern;
        }

        return (bool) @preg_match($pattern, $ip);
    }
}
