<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

final class Config
{
    private const DEFAULTS = [
        'SITE_URL' => 'http://202.test',
        'SITE_PATH' => 'C:/Users/david/sites/202',
        'TRUST_PATH' => 'C:/Users/david/trustpath/202-trust',
        'SNAPSHOT_PATH' => 'C:/Users/david/trustpath/202-snapshot',
        'PRISTINE_PATH' => 'C:/Users/david/trustpath/_backup/202-pristine',
        'SNAPSHOT_DB' => 'protector_test_snapshot',
        'PROFILE' => 'enabled',
        'SITE_PATH_FOR_HASH' => 'C:/Users/david/sites/202',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'DB_NAME' => 'protector_test',
        'DB_PREFIX' => 'ptest',
        'ADMIN_LOGIN' => 'admin',
        'ADMIN_EMAIL' => 'admin@example.test',
    ];

    private array $values;

    public function __construct(private readonly string $envFile)
    {
        $this->values = self::DEFAULTS;

        foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if ($line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $this->values[trim($key)] = $value;
        }

        $profile = getenv('PROTECTOR_PROFILE');

        if (is_string($profile) && $profile !== '') {
            $this->values['PROFILE'] = $profile;
        }
    }

    public static function load(): self
    {
        return new self(dirname(__DIR__) . '/.env');
    }

    public function get(string $key): string
    {
        return $this->values[$key] ?? throw new \OutOfBoundsException("Unknown config key {$key}");
    }

    public function adminPassword(): string
    {
        if (!isset($this->values['ADMIN_PASSWORD'])) {
            $this->persist('ADMIN_PASSWORD', bin2hex(random_bytes(12)));
        }

        return $this->values['ADMIN_PASSWORD'];
    }

    public function dbSalt(): string
    {
        if (!isset($this->values['DB_SALT'])) {
            $this->persist('DB_SALT', bin2hex(random_bytes(16)));
        }

        return $this->values['DB_SALT'];
    }

    public function repoRoot(): string
    {
        return str_replace('\\', '/', dirname(__DIR__, 3));
    }

    public function pdo(?string $database = null): \PDO
    {
        $dsn = "mysql:host={$this->get('DB_HOST')};port={$this->get('DB_PORT')};charset=utf8mb4";

        if ($database !== null) {
            $dsn .= ";dbname={$database}";
        }

        return new \PDO($dsn, $this->get('DB_USER'), $this->get('DB_PASS'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private function persist(string $key, string $value): void
    {
        $this->values[$key] = $value;
        file_put_contents($this->envFile, "{$key}={$value}\n", FILE_APPEND | LOCK_EX);
    }
}
