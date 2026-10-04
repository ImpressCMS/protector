<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class IniFlagCheck implements Check
{
    public function __construct(
        private readonly string $setting,
        private readonly bool $enabled,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
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
