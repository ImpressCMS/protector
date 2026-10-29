<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

final class Config
{
    /**
     * @readonly
     */
    private string $envFile;
    private const DEFAULTS = [
        'SITE_URL' => 'http://127.0.0.1:8204',
        'SITE_PATH' => 'C:/Users/david/trustpath/204-site',
        'TRUST_PATH' => 'C:/Users/david/trustpath/204-trust',
        'SNAPSHOT_PATH' => 'C:/Users/david/trustpath/204-snapshot',
        'PRISTINE_PATH' => 'C:/Users/david/trustpath/_backup/204-pristine',
        'SNAPSHOT_DB' => 'protector_test204_snapshot',
        'SITE_PATH_FOR_HASH' => 'C:/Users/david/trustpath/204-site',
        'DB_HOST' => '127.0.0.1',
        'DB_PORT' => '3306',
        'DB_USER' => 'root',
        'DB_PASS' => '',
        'DB_NAME' => 'protector_test204',
        'DB_PREFIX' => 'ptest',
        'ADMIN_LOGIN' => 'admin',
        'ADMIN_EMAIL' => 'admin@example.test',
        'BUNDLE_FROM_CORE' => '0',
    ];

    private array $values;

    public function __construct(string $envFile)
    {
        $this->envFile = $envFile;
        $this->values = self::DEFAULTS;

        foreach (is_file($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [] as $line) {
            if ($line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $this->values[trim($key)] = $value;
        }
    }

    public static function load(): self
    {
        $file = getenv('PROTECTOR_ENV_FILE');

        return new self(is_string($file) && $file !== '' ? $file : dirname(__DIR__) . '/.env');
    }

    /**
     * Any key can be overridden for one run with an environment variable named PROTECTOR_<KEY>.
     */
    public function get(string $key): string
    {
        $override = getenv('PROTECTOR_' . $key);

        if (is_string($override) && $override !== '') {
            return $override;
        }
        if (!isset($this->values[$key])) {
            throw new \OutOfBoundsException("Unknown config key {$key}");
        }
        return $this->values[$key];
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

    /**
     * Directory that contains the module tree under test (root/ and trust_path/). PROTECTOR_MODULE_SOURCE points
     * it at another checkout, for example an older release exported with "git archive", for upgrade scenarios.
     */
    public function repoRoot(): string
    {
        $override = getenv('PROTECTOR_MODULE_SOURCE');

        if (is_string($override) && $override !== '') {
            return rtrim(str_replace('\\', '/', $override), '/');
        }

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
