<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Site;

use ImpressCMS\Module\Protector\Tests\Functional\Layout;

/**
 * Two ways to run the suite against the same module code:
 *
 *  - "as-is":   the module exactly as it is in the repository. On ImpressCMS 2.1 this means the postcheck stage
 *               never runs (defect S7) and every filter_input(INPUT_SERVER) is null (defect D4).
 *  - "enabled": the same code with those defects (and the S11 signature fatal) patched in the INSTALLED COPY only (never in the repository),
 *               so the suite can record what Protector is intended to do and protect that during the refactoring.
 *
 * The patches are idempotent and become no-ops once the code no longer contains the patterns.
 */
final class CompatibilityProfile
{
    public const AS_IS = 'as-is';

    public const ENABLED = 'enabled';

    public function __construct(private readonly Layout $layout)
    {
    }

    /**
     * @return list<string> descriptions of the patches that changed something
     */
    public function apply(string $profile): array
    {
        if ($profile === self::AS_IS) {
            return [];
        }

        $applied = [];
        $moduleDir = $this->layout->liveTrustModuleDir();

        foreach (['include/precheck.inc.php', 'include/postcheck.inc.php'] as $relative) {
            $changed = $this->replaceInFile(
                "{$moduleDir}/{$relative}",
                'Icms\\Db\\Legacy\\icms_db_legacy_Factory',
                'Icms\\Db\\Legacy\\Factory',
            );

            if ($changed) {
                $applied[] = "S7: factory class name in {$relative}";
            }
        }

        $changed = $this->replaceByPattern(
            "{$moduleDir}/class/protector.php",
            '/filter_input\(INPUT_SERVER,\s*\'(\w+)\',\s*(FILTER_\w+)\)/',
            'filter_var($_SERVER[\'$1\'] ?? \'\', $2)',
        );

        if ($changed) {
            $applied[] = 'D4: filter_input(INPUT_SERVER) in class/protector.php';
        }

        $changed = $this->replaceInFile(
            "{$moduleDir}/class/ProtectorMysqlDatabase.class.php",
            'function query(string $sql, int $limit = 0, int $start = 0)',
            'function query(string $sql, ?int $limit = 0, ?int $start = 0)',
        );

        if ($changed) {
            $applied[] = 'S11: query() signature in class/ProtectorMysqlDatabase.class.php';
        }

        return $applied;
    }

    private function replaceInFile(string $file, string $search, string $replace): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $content = (string) file_get_contents($file);

        if (!str_contains($content, $search)) {
            return false;
        }

        file_put_contents($file, str_replace($search, $replace, $content));

        return true;
    }

    private function replaceByPattern(string $file, string $pattern, string $replacement): bool
    {
        if (!is_file($file)) {
            return false;
        }

        $content = (string) file_get_contents($file);
        $patched = (string) preg_replace($pattern, $replacement, $content, -1, $count);

        if ($count === 0) {
            return false;
        }

        file_put_contents($file, $patched);

        return true;
    }
}
