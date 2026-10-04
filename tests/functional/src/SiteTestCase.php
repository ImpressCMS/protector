<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

use ImpressCMS\Module\Protector\Tests\Functional\Http\Client;
use ImpressCMS\Module\Protector\Tests\Functional\Http\Response;
use ImpressCMS\Module\Protector\Tests\Functional\Site\AdminSession;
use PHPUnit\Framework\TestCase;

/**
 * Base class: every test starts from the golden snapshot's data (log and access tables empty, default
 * preferences, no ban files) and talks to the site only over HTTP, the database and the data files.
 */
abstract class SiteTestCase extends TestCase
{
    /** an address that is not covered by the default "reliable IPs" (^192.168. | 127.0.0.1) */
    protected const ATTACKER = '127.0.0.2';

    /** the plain loopback address, which the default "reliable IPs" setting treats as trusted */
    protected const TRUSTED = '127.0.0.1';

    private static ?array $defaultPreferences = null;

    protected static function config(): Config
    {
        return $GLOBALS['protector_functional']['config'];
    }

    protected static function layout(): Layout
    {
        return $GLOBALS['protector_functional']['layout'];
    }

    protected function setUp(): void
    {
        $this->resetState();
    }

    protected function resetState(): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');

        foreach (['protector_log', 'protector_access', 'session'] as $table) {
            $pdo->exec("TRUNCATE TABLE `{$prefix}_{$table}`");
        }

        $update = $pdo->prepare("UPDATE `{$prefix}_config` SET conf_value = :value WHERE conf_name = :name AND conf_title LIKE '\\_MI\\_PROTECTOR%'");

        foreach (self::defaultPreferences() as $name => $value) {
            $update->execute(['value' => $value, 'name' => $name]);
        }

        self::layout()->clearRuntimeData();
        $this->warmUp();
    }

    /**
     * Protector reads its preferences from a cache file that is rewritten at the end of the first request after a
     * change, so every preference change is followed by one harmless request from a trusted address.
     */
    protected function warmUp(): void
    {
        (new Client(self::config()->get('SITE_URL'), self::TRUSTED))->get('/probe.php', ['warmup' => 1]);
    }

    /**
     * @param array<string, int|string|array<int|string>> $preferences preference name => value
     */
    protected function configure(array $preferences): void
    {
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));
        $prefix = self::config()->get('DB_PREFIX');
        $update = $pdo->prepare("UPDATE `{$prefix}_config` SET conf_value = :value WHERE conf_name = :name AND conf_title LIKE '\\_MI\\_PROTECTOR%'");

        foreach ($preferences as $name => $value) {
            $stored = is_array($value) ? serialize(array_map('strval', $value)) : (string) $value;
            $update->execute(['value' => $stored, 'name' => $name]);
        }

        $this->warmUp();
    }

    protected function client(string $ip = self::ATTACKER, ?string $userAgent = null): Client
    {
        return new Client(self::config()->get('SITE_URL'), $ip, $userAgent ?? 'ProtectorFunctionalTest/1.0');
    }

    protected function admin(string $ip = self::TRUSTED): AdminSession
    {
        $session = new AdminSession(self::config(), $ip);
        $session->login();

        return $session;
    }

    /**
     * Saves the "bad IPs" and "group 1 IPs" text areas on the module's admin start page.
     */
    protected function saveIpLists(AdminSession $admin, string $badIps, string $groupOneIps = ''): Response
    {
        $page = $admin->page('/modules/protector/admin/index.php');

        return $admin->submitForm($page, 'ConfigForm', ['action' => 'update_ips', 'bad_ips' => $badIps, 'group1_ips' => $groupOneIps]);
    }

    protected function isServed(Response $response): bool
    {
        return $response->status === 200 && str_starts_with(ltrim($response->body), '{') && str_contains($response->body, '"get"');
    }

    protected function visibleText(Response $response): string
    {
        $withoutCode = (string) preg_replace('#<(script|style)\b.*?</\1>#is', '', $response->body);

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($withoutCode)));
    }

    /**
     * @param array<string, mixed> $query
     */
    protected function probe(Client $client, array $query = []): Response
    {
        return $client->get('/probe.php', $query);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function logRows(): array
    {
        $prefix = self::config()->get('DB_PREFIX');

        return self::config()->pdo(self::config()->get('DB_NAME'))
            ->query("SELECT lid, uid, ip, type, agent, description FROM `{$prefix}_protector_log` ORDER BY lid")
            ->fetchAll();
    }

    /**
     * @return list<string>
     */
    protected function logTypes(): array
    {
        return array_map(static fn (array $row): string => $row['type'], $this->logRows());
    }

    protected function accessCount(?string $ip = null): int
    {
        $prefix = self::config()->get('DB_PREFIX');
        $pdo = self::config()->pdo(self::config()->get('DB_NAME'));

        if ($ip === null) {
            return (int) $pdo->query("SELECT COUNT(*) FROM `{$prefix}_protector_access`")->fetchColumn();
        }

        $statement = $pdo->prepare("SELECT COUNT(*) FROM `{$prefix}_protector_access` WHERE ip = :ip");
        $statement->execute(['ip' => $ip]);

        return (int) $statement->fetchColumn();
    }

    protected function preference(string $name): string
    {
        $prefix = self::config()->get('DB_PREFIX');
        $statement = self::config()->pdo(self::config()->get('DB_NAME'))
            ->prepare("SELECT conf_value FROM `{$prefix}_config` WHERE conf_name = :name AND conf_title LIKE '\\_MI\\_PROTECTOR%'");
        $statement->execute(['name' => $name]);

        return (string) $statement->fetchColumn();
    }

    /**
     * @return array<string, string> preference name => stored value, as installed by the module
     */
    private static function defaultPreferences(): array
    {
        if (self::$defaultPreferences !== null) {
            return self::$defaultPreferences;
        }

        $config = self::config();
        $prefix = $config->get('DB_PREFIX');
        $rows = $config->pdo($config->get('SNAPSHOT_DB'))
            ->query("SELECT conf_name, conf_value FROM `{$prefix}_config` WHERE conf_title LIKE '\\_MI\\_PROTECTOR%'")
            ->fetchAll(\PDO::FETCH_KEY_PAIR);

        return self::$defaultPreferences = $rows;
    }
}
