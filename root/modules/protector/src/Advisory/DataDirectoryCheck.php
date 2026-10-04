<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DataDirectoryCheck implements Check
{
    public function __construct(
        private readonly string $directory,
        private readonly string $trustPath,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
    }

    public function run(): CheckResult
    {
        $shown = str_replace($this->trustPath, 'TRUSTPATH', $this->directory);

        if (is_writable($this->directory)) {
            return CheckResult::ok('data directory', "{$shown} writable");
        }

        return CheckResult::problem('data directory', "{$shown} not writable", $this->notSecureText, $this->advice);
    }
}
