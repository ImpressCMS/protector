<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DatabasePrefixCheck implements Check
{
    /**
     * @readonly
     */
    private string $prefix;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    public function __construct(string $prefix, string $notSecureText, string $advice)
    {
        $this->prefix = $prefix;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
    }

    public function run(): CheckResult
    {
        if (strtolower($this->prefix) !== 'xoops') {
            return CheckResult::ok('XOOPS_DB_PREFIX', $this->prefix);
        }

        return CheckResult::problem('XOOPS_DB_PREFIX', $this->prefix, $this->notSecureText, $this->advice);
    }
}
