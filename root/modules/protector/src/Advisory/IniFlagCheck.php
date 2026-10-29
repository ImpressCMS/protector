<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class IniFlagCheck implements Check
{
    /**
     * @readonly
     */
    private string $setting;
    /**
     * @readonly
     */
    private bool $enabled;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    public function __construct(string $setting, bool $enabled, string $notSecureText, string $advice)
    {
        $this->setting = $setting;
        $this->enabled = $enabled;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
    }

    public function run(): CheckResult
    {
        $subject = $this->setting;

        if (!$this->enabled) {
            return CheckResult::ok($subject, 'off');
        }

        return CheckResult::problem($subject, 'on', $this->notSecureText, $this->advice);
    }
}
