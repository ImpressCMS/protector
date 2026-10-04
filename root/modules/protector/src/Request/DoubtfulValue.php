<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

final readonly class DoubtfulValue
{
    /** @param array<int, int|string> $path */
    public function __construct(
        public string $source,
        public array $path,
        public string $value,
    ) {
    }
}
