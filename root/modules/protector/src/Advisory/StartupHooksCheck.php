<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class StartupHooksCheck implements Check
{
    public function __construct(
        private readonly bool $precheckRan,
        private readonly bool $postcheckRan,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
    }

    public function run(): CheckResult
    {
        if (!$this->precheckRan) {
            return CheckResult::problem('mainfile.php', 'missing precheck', $this->notSecureText, $this->advice);
        }

        if (!$this->postcheckRan) {
            return CheckResult::problem('mainfile.php', 'missing postcheck', $this->notSecureText, $this->advice);
        }

        return CheckResult::ok('mainfile.php', 'patched');
    }
}
