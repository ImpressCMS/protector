<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

final class RequestMutator
{
    /** @param array<int, int|string> $path */
    public function replace(string $source, array $path, string $value): void
    {
        $main = &$this->locateInSource($source, $path);

        if (!isset($main)) {
            return;
        }

        $request = &$this->locate($_REQUEST, $path);

        if ($request !== false && $main == $request) {
            $request = $value;
        }

        $main = $value;
    }

    /** @param array<int, int|string> $path */
    private function &locateInSource(string $source, array $path): mixed
    {
        switch ($source) {
            case 'G':
                return $this->locate($_GET, $path);
            case 'P':
                return $this->locate($_POST, $path);
            case 'C':
                return $this->locate($_COOKIE, $path);
        }

        $nothing = null;

        return $nothing;
    }

    /** @param array<int, int|string> $path */
    private function &locate(array &$root, array $path): mixed
    {
        $current = &$root;

        foreach ($path as $key) {
            if (!is_array($current)) {
                $missing = false;

                return $missing;
            }

            $current = &$current[$key];
        }

        return $current;
    }
}
