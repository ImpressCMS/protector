<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DatabasePrefixCheck implements Check
{
    public function __construct(
        private readonly string $prefix,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
    }

    public function run(): CheckResult
    {
        if (strtolower($this->prefix) !== 'xoops') {
            return CheckResult::ok('XOOPS_DB_PREFIX', $this->prefix);
        }

        return CheckResult::problem('XOOPS_DB_PREFIX', $this->prefix, $this->notSecureText, $this->advice);
    }
}
