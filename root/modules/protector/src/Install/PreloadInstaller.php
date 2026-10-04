<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Install;

final class PreloadInstaller
{
    public function __construct(
        private readonly string $source,
        private readonly string $preloadDirectory,
        private readonly bool $startupAlreadyRan,
    ) {
    }

    public static function forCurrentSite(): self
    {
        return new self(
            dirname(__DIR__, 2) . '/preload/protector.php',
            defined('ICMS_PRELOAD_PATH') ? ICMS_PRELOAD_PATH : '',
            defined('PROTECTOR_PRECHECK_INCLUDED') && defined('PROTECTOR_POSTCHECK_INCLUDED'),
        );
    }

    public function ensure(): string
    {
        $target = "{$this->preloadDirectory}/protector.php";

        if ($this->preloadDirectory === '' || is_file($target) || $this->startupAlreadyRan) {
            return '';
        }

        if (@copy($this->source, $target)) {
            return 'Protector preload copied to the preloads directory.';
        }

        return 'Failed to copy the Protector preload: your site is not protected.';
    }

    public function remove(): void
    {
        $target = "{$this->preloadDirectory}/protector.php";

        if ($this->preloadDirectory !== '' && is_file($target)) {
            @unlink($target);
        }
    }
}
