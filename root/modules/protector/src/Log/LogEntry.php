<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

final readonly class LogEntry
{
    public function __construct(
        public int $id,
        public int $uid,
        public string $ip,
        public string $agent,
        public string $type,
        public string $description,
        public int $timestamp,
        public ?string $userName,
    ) {
    }
}
