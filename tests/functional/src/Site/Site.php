<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Site;

use ImpressCMS\Module\Protector\Tests\Functional\Config;
use ImpressCMS\Module\Protector\Tests\Functional\FileSystem;
use ImpressCMS\Module\Protector\Tests\Functional\Layout;

/**
 * Lifecycle of the throw-away ImpressCMS site used by the suite:
 * pristine -> install (via the web wizard) -> snapshot -> restore (before every run).
 */
final class Site
{
    private const SNAPSHOT_FILES = [
        'mainfile.php' => 'mainfile.php',
        'modules/protector' => 'modules/protector',
        'plugins/preloads/protector.php' => 'plugins/preloads/protector.php',
    ];

    private const CLEARED_DIRECTORIES = ['cache', 'templates_c', 'uploads'];

    public function __construct(
        private readonly Config $config,
        private readonly Layout $layout,
    ) {
    }

    public function pristine(): void
    {
        $pristine = FileSystem::normalize($this->config->get('PRISTINE_PATH'));

        if (!is_dir($pristine . '/install')) {
            throw new \RuntimeException("No pristine copy of the site at {$pristine}");
        }

        FileSystem::mirror($pristine, $this->layout->sitePath());
        FileSystem::removeTree($this->layout->trustPath());
        mkdir($this->layout->trustPath(), 0777, true);

        (new DbSnapshot($this->config))->dropDatabase($this->config->get('DB_NAME'));
    }

    public function install(callable $progress): void
    {
        $progress('resetting site to pristine state');
        $this->pristine();

        $progress('copying the module under test into the installer bundle');
        $this->layout->syncIntoInstallBundle();

        (new WizardInstaller($this->config))->run($progress);
        $this->deployFixtures();

        $progress('install finished');
    }

    public function snapshot(callable $progress): void
    {
        $snapshot = FileSystem::normalize($this->config->get('SNAPSHOT_PATH'));
        FileSystem::removeTree($snapshot);
        mkdir($snapshot, 0777, true);

        FileSystem::copyTree($this->layout->trustPath(), $snapshot . '/trust');

        foreach (self::SNAPSHOT_FILES as $relative => $_) {
            $source = $this->layout->sitePath() . '/' . $relative;
            $target = $snapshot . '/site/' . $relative;
            is_dir(dirname($target)) || mkdir(dirname($target), 0777, true);
            is_dir($source) ? FileSystem::copyTree($source, $target) : copy($source, $target);
        }

        $tables = (new DbSnapshot($this->config))->create();
        $progress("snapshot written ({$tables} tables)");
    }

    public function deployFixtures(): void
    {
        $source = dirname(__DIR__, 2) . '/fixtures';

        foreach (glob($source . '/*.php') ?: [] as $file) {
            copy($file, $this->layout->sitePath() . '/' . basename($file));
        }
    }

    public function restore(): void
    {
        $snapshot = FileSystem::normalize($this->config->get('SNAPSHOT_PATH'));

        if (!is_dir($snapshot . '/trust')) {
            throw new \RuntimeException('No snapshot found; run "site.php install" and "site.php snapshot" first.');
        }

        FileSystem::mirror($snapshot . '/trust', $this->layout->trustPath());

        foreach (self::SNAPSHOT_FILES as $relative => $_) {
            $source = $snapshot . '/site/' . $relative;
            $target = $this->layout->sitePath() . '/' . $relative;

            if (is_dir($source)) {
                FileSystem::mirror($source, $target);

                continue;
            }

            @chmod($target, 0666);
            copy($source, $target);
        }

        foreach (self::CLEARED_DIRECTORIES as $directory) {
            FileSystem::emptyDirectory($this->layout->sitePath() . '/' . $directory);
        }

        (new DbSnapshot($this->config))->restore();
        $this->deployFixtures();
    }
}
