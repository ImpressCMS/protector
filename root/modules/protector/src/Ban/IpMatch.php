<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

final readonly class IpMatch
{
    public function __construct(
        public string $pattern,
        public mixed $info,
    ) {
    }
}
