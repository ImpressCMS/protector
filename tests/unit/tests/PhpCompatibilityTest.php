<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The module has to run on PHP 7.4 up to 8.4 (ImpressCMS 2.0.4). A parse check with the real binaries of the other
 * versions is the strongest guard and is optional (PROTECTOR_PHP_BINARIES); the token scan below runs everywhere and
 * finds what a parse check cannot: functions that only exist in newer PHP versions.
 */
final class PhpCompatibilityTest extends TestCase
{
    private const NEWER_FUNCTIONS = [
        'str_contains', 'str_starts_with', 'str_ends_with', 'array_is_list', 'get_debug_type', 'get_resource_id', 'fdiv',
        'preg_last_error_msg', 'enum_exists', 'mb_str_pad', 'json_validate', 'array_find', 'array_any', 'array_all',
        'array_find_key', 'mb_trim', 'iterator_apply',
    ];

    private const NEWER_TOKENS = ['T_MATCH', 'T_ENUM', 'T_READONLY', 'T_NULLSAFE_OBJECT_OPERATOR', 'T_ATTRIBUTE'];

    public function testNoFunctionThatOlderPhpVersionsLackIsCalled(): void
    {
        $found = [];

        foreach ($this->moduleFiles() as $file) {
            $tokens = token_get_all((string) file_get_contents($file));

            foreach ($tokens as $position => $token) {
                if (!is_array($token) || $token[0] !== T_STRING || !in_array(strtolower($token[1]), self::NEWER_FUNCTIONS, true)) {
                    continue;
                }

                if ($this->isFunctionCall($tokens, $position)) {
                    $found[] = "{$file}:{$token[2]} {$token[1]}()";
                }
            }
        }

        self::assertSame([], $found);
    }

    public function testNoSyntaxFromNewerPhpVersionsIsUsed(): void
    {
        $banned = array_filter(array_map(static fn (string $name): ?int => defined($name) ? constant($name) : null, self::NEWER_TOKENS));
        $found = [];

        foreach ($this->moduleFiles() as $file) {
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (is_array($token) && in_array($token[0], $banned, true)) {
                    $found[] = "{$file}:{$token[2]} " . token_name($token[0]);
                }
            }
        }

        self::assertSame([], $found);
    }

    public function testConstructorPromotionAndUnionTypesAreNotUsed(): void
    {
        $found = [];

        foreach ($this->moduleFiles() as $file) {
            $source = $this->codeWithoutComments($file);

            if (preg_match('/function\s+__construct\s*\([^)]*\b(?:private|protected|public)\s+(?:readonly\s+)?[\\\\?A-Za-z$]/s', $source) === 1) {
                $found[] = "{$file}: constructor promotion";
            }

            if (preg_match('/function\s+\w+\s*\([^)]*\)\s*:\s*\??[A-Za-z\\\\]+\s*\|/s', $source) === 1 || preg_match('/\(\s*(?:[A-Za-z\\\\]+\|[A-Za-z\\\\|]+)\s+\$\w+/', $source) === 1) {
                $found[] = "{$file}: union type";
            }

            if (preg_match('/:\s*(?:mixed|never|static)\s*[{;]/', $source) === 1 || preg_match('/\bmixed\s+\$\w+/', $source) === 1) {
                $found[] = "{$file}: mixed, never or static type";
            }
        }

        self::assertSame([], $found);
    }

    public function testEveryFileParsesOnTheOlderPhpBinariesThatAreConfigured(): void
    {
        $binaries = array_filter(explode(';', (string) getenv('PROTECTOR_PHP_BINARIES')));

        if ($binaries === []) {
            self::markTestSkipped('Set PROTECTOR_PHP_BINARIES to a ";" separated list of php executables (7.4, 8.0, ...) to run the parse check.');
        }

        $failures = [];

        foreach ($binaries as $binary) {
            foreach ($this->moduleFiles() as $file) {
                $output = [];
                exec(escapeshellarg($binary) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output);

                if (strpos(implode("\n", $output), 'No syntax errors') === false) {
                    $failures[] = "{$binary}: {$file}: " . implode(' ', $output);
                }
            }
        }

        self::assertSame([], $failures);
    }

    private function codeWithoutComments(string $file): string
    {
        $code = '';

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /**
     * @param array<int, mixed> $tokens
     */
    private function isFunctionCall(array $tokens, int $position): bool
    {
        $before = $position - 1;

        while ($before >= 0 && is_array($tokens[$before]) && $tokens[$before][0] === T_WHITESPACE) {
            $before--;
        }

        if ($before >= 0 && is_array($tokens[$before]) && in_array($tokens[$before][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
            return false;
        }

        $after = $position + 1;

        while (isset($tokens[$after]) && is_array($tokens[$after]) && $tokens[$after][0] === T_WHITESPACE) {
            $after++;
        }

        return ($tokens[$after] ?? null) === '(';
    }

    /**
     * @return list<string>
     */
    private function moduleFiles(): array
    {
        $files = [dirname(__DIR__, 3) . '/bin/build-package'];
        $root = dirname(__DIR__, 3) . '/root/modules/protector';

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
