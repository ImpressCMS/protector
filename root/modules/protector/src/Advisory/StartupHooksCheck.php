<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class StartupHooksCheck implements Check
{
    /**
     * @readonly
     */
    private bool $precheckRan;
    /**
     * @readonly
     */
    private bool $postcheckRan;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    public function __construct(bool $precheckRan, bool $postcheckRan, string $notSecureText, string $advice)
    {
        $this->precheckRan = $precheckRan;
        $this->postcheckRan = $postcheckRan;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
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
