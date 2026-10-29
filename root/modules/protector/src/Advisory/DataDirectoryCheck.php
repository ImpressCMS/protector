<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class DataDirectoryCheck implements Check
{
    /**
     * @readonly
     */
    private string $directory;
    /**
     * @readonly
     */
    private string $trustPath;
    /**
     * @readonly
     */
    private string $notSecureText;
    /**
     * @readonly
     */
    private string $advice;
    public function __construct(string $directory, string $trustPath, string $notSecureText, string $advice)
    {
        $this->directory = $directory;
        $this->trustPath = $trustPath;
        $this->notSecureText = $notSecureText;
        $this->advice = $advice;
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
