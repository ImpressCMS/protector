<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class TrustPathCheck implements Check
{
    /**
     * @readonly
     */
    private string $rootPath;
    /**
     * @readonly
     */
    private string $trustPath;
    /**
     * @readonly
     */
    private string $siteUrl;
    /**
     * @readonly
     */
    private string $advice;
    /**
     * @readonly
     */
    private string $linkText;
    public function __construct(string $rootPath, string $trustPath, string $siteUrl, string $advice, string $linkText)
    {
        $this->rootPath = $rootPath;
        $this->trustPath = $trustPath;
        $this->siteUrl = $siteUrl;
        $this->advice = $advice;
        $this->linkText = $linkText;
    }

    public function run(): CheckResult
    {
        $base = "{$this->siteUrl}/{$this->relativeTrustPath()}/modules/protector";

        return new CheckResult(
            'ICMS_TRUST_PATH',
            '',
            true,
            '',
            $this->advice,
            "{$base}/public_check.png",
            "{$base}/public_check.php",
            $this->linkText,
        );
    }

    public function relativeTrustPath(): string
    {
        $root = explode('/', $this->rootPath);
        $trust = explode('/', $this->trustPath);
        $common = 0;

        while (isset($root[$common], $trust[$common]) && $root[$common] === $trust[$common]) {
            $common++;
        }

        return str_repeat('../', count($root) - $common) . implode('/', array_slice($trust, $common));
    }
}
