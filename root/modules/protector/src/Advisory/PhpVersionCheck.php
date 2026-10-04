<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class PhpVersionCheck implements Check
{
    private const MINIMUM = '8.2.0';

    public function __construct(
        private readonly string $version,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
    }

    public function run(): CheckResult
    {
        if (version_compare($this->version, self::MINIMUM, '>=')) {
            return CheckResult::ok('PHP', $this->version);
        }

        return CheckResult::problem('PHP', $this->version, $this->notSecureText, $this->advice);
    }
}
