<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

use ImpressCMS\Module\Protector\Ban\IpComparator;

final class IpListParser
{
    private const FOREVER = 0x7fffffff;

    private const MAXIMUM_ADDRESS_LENGTH = 15;

    /** @return array<string, int> */
    public function parseBadIps(string $text): array
    {
        $entries = [];

        foreach ($this->lines($text) as $line) {
            [$address, $jailedUntil] = array_pad(explode(':', $line, 2), 2, '');
            $address = trim($address);

            if ($address !== '' && $this->isAddress($address)) {
                $entries[$address] = empty($jailedUntil) ? self::FOREVER : (int) $jailedUntil;
            }
        }

        return $entries;
    }

    /** @return list<string> */
    public function parseGroupOneIps(string $text): array
    {
        $addresses = [];

        foreach ($this->lines($text) as $line) {
            $address = trim($line);

            if ($address !== '' && $this->isAddress($address)) {
                $addresses[$address] = $address;
            }
        }

        return array_values($addresses);
    }

    /** @param array<string, int> $entries */
    public function formatBadIps(array $entries): string
    {
        uksort($entries, static fn (int|string $first, int|string $second): int => IpComparator::compare((string) $first, (string) $second));

        $text = '';

        foreach ($entries as $address => $jailedUntil) {
            $text .= ($jailedUntil && $jailedUntil !== self::FOREVER ? "{$address}:{$jailedUntil}" : (string) $address) . "\n";
        }

        return $text;
    }

    /** @param array<int|string, int|string> $addresses */
    public function formatGroupOneIps(array $addresses): string
    {
        $addresses = array_map('strval', array_values($addresses));
        usort($addresses, IpComparator::compare(...));

        return implode("\n", $addresses);
    }

    /** @return list<string> */
    private function lines(string $text): array
    {
        return $text === '' ? [] : explode("\n", trim($text));
    }

    private function isAddress(string $value): bool
    {
        return !preg_match('/[^0-9\.]/', $value) && strlen($value) <= self::MAXIMUM_ADDRESS_LENGTH;
    }
}
