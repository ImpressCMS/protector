<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

/**
 * The only class that knows where the module's files live, in the repository and in the test site.
 * Phase 1 (single module directory) changes this class and nothing else in the suite.
 */
final class Layout
{
    public function __construct(private readonly Config $config)
    {
    }

    public function repoRootTree(): string
    {
        return $this->config->repoRoot() . '/root';
    }

    public function repoTrustTree(): string
    {
        return $this->config->repoRoot() . '/trust_path';
    }

    public function sitePath(): string
    {
        return FileSystem::normalize($this->config->get('SITE_PATH'));
    }

    public function trustPath(): string
    {
        return FileSystem::normalize($this->config->get('TRUST_PATH'));
    }

    public function installBundleDir(): string
    {
        return $this->sitePath() . '/install/modules/protector';
    }

    public function liveModuleDir(): string
    {
        return $this->sitePath() . '/modules/protector';
    }

    public function liveTrustModuleDir(): string
    {
        return $this->trustPath() . '/modules/protector';
    }

    public function preloadFile(): string
    {
        return $this->sitePath() . '/plugins/preloads/protector.php';
    }

    public function dataDir(): string
    {
        return $this->liveTrustModuleDir() . '/configs';
    }

    public function badIpsFile(): ?string
    {
        return $this->glob('badips');
    }

    public function bandwidthFile(): ?string
    {
        return $this->glob('bwlimit');
    }

    public function group1IpsFile(): ?string
    {
        return $this->glob('group1ips');
    }

    public function configCacheFile(): ?string
    {
        return $this->glob('configcache');
    }

    public function customFilterDir(): string
    {
        return $this->liveModuleDir() . '/filters_byconfig';
    }

    public function databaseTrapClassFile(): string
    {
        return $this->liveModuleDir() . '/class/ProtectorMysqlDatabase.class.php';
    }

    public function siteHtaccess(): string
    {
        return $this->sitePath() . '/.htaccess';
    }

    public function htaccessBackup(): string
    {
        return $this->sitePath() . '/uploads/.htaccess.bak';
    }

    /**
     * The admin form cannot store a usable "allowed IPs for group 1" list (defect D1), so scenarios that protect the
     * intended behaviour write the list in the module's own storage format.
     *
     * @param list<string> $addresses
     */
    public function writeGroupOneIps(array $addresses): void
    {
        $suffix = substr(md5($this->config->get('SITE_PATH_FOR_HASH') . $this->config->get('DB_USER') . $this->config->get('DB_PREFIX')), 0, 6);
        file_put_contents($this->dataDir() . "/group1ips{$suffix}", serialize(array_values($addresses)) . PHP_EOL);
    }

    /**
     * Removes ban lists, bandwidth marker and config cache so each test starts without state.
     */
    public function clearRuntimeData(): void
    {
        foreach ([$this->siteHtaccess(), $this->htaccessBackup()] as $file) {
            is_file($file) && @unlink($file);
        }

        foreach (['badips', 'bwlimit', 'group1ips', 'configcache'] as $prefix) {
            foreach (glob($this->dataDir() . "/{$prefix}*") ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * Copies the module under test into the installer's bundled-module location.
     */
    public function syncIntoInstallBundle(): void
    {
        FileSystem::mirror($this->repoRootTree(), $this->installBundleDir() . '/root');
        FileSystem::mirror($this->repoTrustTree(), $this->installBundleDir() . '/trust_path');
    }

    /**
     * Copies the module under test into an already installed site.
     */
    public function syncIntoLiveSite(): void
    {
        FileSystem::mirror($this->repoRootTree() . '/modules/protector', $this->liveModuleDir());
        FileSystem::mirror(
            $this->repoTrustTree() . '/modules/protector',
            $this->liveTrustModuleDir(),
            [$this->liveTrustModuleDir() . '/configs'],
        );
    }

    private function glob(string $prefix): ?string
    {
        $matches = glob($this->dataDir() . "/{$prefix}*") ?: [];

        return $matches[0] ?? null;
    }
}
