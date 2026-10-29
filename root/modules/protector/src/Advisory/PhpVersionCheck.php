<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class PhpVersionCheck implements Check
{
    /**
     * @readonly
     */
    private string $version;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    private const MINIMUM = '8.2.0';

    public function __construct(string $version, string $notSecureText, string $advice)
    {
        $this->version = $version;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
    }

    public function run(): CheckResult
    {
        if (version_compare($this->version, self::MINIMUM, '>=')) {
            return CheckResult::ok('PHP', $this->version);
        }

        return CheckResult::problem('PHP', $this->version, $this->notSecureText, $this->advice);
    }
}
