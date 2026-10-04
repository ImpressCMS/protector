<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class TrustPathCheck implements Check
{
    public function __construct(
        private readonly string $rootPath,
        private readonly string $trustPath,
        private readonly string $siteUrl,
        private readonly string $advice,
        private readonly string $linkText,
    ) {
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
