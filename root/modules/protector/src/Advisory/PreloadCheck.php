<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class PreloadCheck implements Check
{
    public function __construct(
        private readonly string $preloadDirectory,
        private readonly string $notSecureText,
        private readonly string $advice,
    ) {
    }

    public function run(): CheckResult
    {
        if (is_file("{$this->preloadDirectory}/protector.php")) {
            return CheckResult::ok('preload', 'plugins/preloads/protector.php present');
        }

        return CheckResult::problem('preload', 'plugins/preloads/protector.php missing', $this->notSecureText, $this->advice);
    }
}
