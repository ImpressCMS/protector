<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class PreloadCheck implements Check
{
    /**
     * @readonly
     */
    private string $preloadDirectory;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    public function __construct(string $preloadDirectory, string $notSecureText, string $advice)
    {
        $this->preloadDirectory = $preloadDirectory;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
    }

    public function run(): CheckResult
    {
        if (is_file("{$this->preloadDirectory}/protector.php")) {
            return CheckResult::ok('preload', 'plugins/preloads/protector.php present');
        }

        return CheckResult::problem('preload', 'plugins/preloads/protector.php missing', $this->notSecureText, $this->advice);
    }
}
