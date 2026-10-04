<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Output;

final class XssUmbrella
{
    /** @var array<int, string> */
    private array $doubtful = [];

    public function start(): void
    {
        $this->doubtful = [];
        $this->collect($_GET);
        $this->collect((string) ($_SERVER['PHP_SELF'] ?? ''));

        if ($this->doubtful !== []) {
            ob_start($this->filter(...));
        }
    }

    public function filter(string $output): string
    {
        if (defined('BIGUMBRELLA_DISABLED')) {
            return $output;
        }

        foreach (headers_list() as $header) {
            if (stristr($header, 'Content-Type:') && !stristr($header, 'text/html')) {
                return $output;
            }
        }

        foreach ($this->doubtful as $value) {
            if (str_contains($output, $value)) {
                return 'XSS found by Protector.';
            }
        }

        return $output;
    }

    private function collect(mixed $value): void
    {
        if (is_array($value)) {
            array_walk($value, $this->collect(...));

            return;
        }

        if (preg_match('/[<\'"].{15}/s', (string) $value, $match)) {
            $this->doubtful[] = $match[0];
        }
    }
}
