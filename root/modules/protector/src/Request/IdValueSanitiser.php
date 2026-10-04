<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

final class IdValueSanitiser
{
    public function apply(): void
    {
        $this->sanitise($_GET);
        $this->sanitise($_POST);
        $this->sanitise($_COOKIE);
    }

    /** @param array<int|string, mixed> $source */
    private function sanitise(array &$source): void
    {
        foreach ($source as $key => $value) {
            if (!str_ends_with((string) $key, 'id') || is_array($value)) {
                continue;
            }

            $clean = (string) preg_replace('/[^0-9a-zA-Z_-]/', '', (string) $value);
            $source[$key] = $clean;

            if (($_REQUEST[$key] ?? null) == $source[$key]) {
                $_REQUEST[$key] = $clean;
            }
        }
    }
}
