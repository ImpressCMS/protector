<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Site;

use ImpressCMS\Module\Protector\Tests\Functional\Config;
use ImpressCMS\Module\Protector\Tests\Functional\Http\Client;
use ImpressCMS\Module\Protector\Tests\Functional\Http\Response;

/**
 * Drives the ImpressCMS web installer (install/page_*.php) over HTTP.
 */
final class WizardInstaller
{
    private Client $client;

    public function __construct(private readonly Config $config)
    {
        $this->client = new Client($config->get('SITE_URL'));
    }

    public function run(callable $progress): void
    {
        $steps = [
            'langselect' => fn () => $this->post('langselect', ['lang' => 'english'], 'start'),
            'start' => fn () => $this->get('start'),
            'modcheck' => fn () => $this->get('modcheck'),
            'pathsettings' => fn () => $this->post('pathsettings', [
                'ROOT_PATH' => $this->config->get('SITE_PATH'),
                'TRUST_PATH' => $this->config->get('TRUST_PATH'),
                'URL' => $this->config->get('SITE_URL'),
            ], 'movevendor'),
            'movevendor' => fn () => $this->post('movevendor', [], 'dbconnection'),
            'dbconnection' => fn () => $this->post('dbconnection', [
                'DB_TYPE' => 'pdo.mysql',
                'DB_HOST' => $this->config->get('DB_HOST'),
                'DB_USER' => $this->config->get('DB_USER'),
                'DB_PASS' => $this->config->get('DB_PASS'),
            ], 'dbsettings'),
            'dbsettings' => fn () => $this->submitDatabaseSettings(),
            'configsave' => fn () => $this->post('configsave', [], 'tablescreate'),
            'tablescreate' => fn () => $this->post('tablescreate', [], null),
            'siteinit' => fn () => $this->post('siteinit', [
                'adminname' => 'Test Administrator',
                'adminlogin_name' => $this->config->get('ADMIN_LOGIN'),
                'adminmail' => $this->config->get('ADMIN_EMAIL'),
                'adminpass' => $this->config->adminPassword(),
                'adminpass2' => $this->config->adminPassword(),
            ], 'tablesfill'),
            'tablesfill' => fn () => $this->post('tablesfill', [], null),
            'modulesinstall' => fn () => $this->post('modulesinstall', ['mod' => '1'], null),
            'end' => fn () => $this->get('end'),
        ];

        foreach ($steps as $name => $step) {
            $progress("wizard step: {$name}");
            $step();
        }
    }

    private function submitDatabaseSettings(): Response
    {
        $fields = [
            'DB_NAME' => $this->config->get('DB_NAME'),
            'DB_CHARSET' => 'utf8mb4',
            'DB_COLLATION' => 'utf8mb4_unicode_ci',
            'DB_PREFIX' => $this->config->get('DB_PREFIX'),
            'DB_SALT' => $this->config->dbSalt(),
        ];

        $first = $this->client->post('/install/page_dbsettings.php', $fields);

        if ($first->isRedirect()) {
            return $first;
        }

        // The wizard reports "Database ... created!" as a message and waits for a second submit.
        return $this->post('dbsettings', $fields, 'configsave');
    }

    private function get(string $page): Response
    {
        $response = $this->client->follow($this->client->get("/install/page_{$page}.php"));
        $this->assertHealthy($page, $response);

        return $response;
    }

    private function post(string $page, array $fields, ?string $expectedNextPage): Response
    {
        $response = $this->client->post("/install/page_{$page}.php", $fields);
        $this->assertHealthy($page, $response);

        if ($expectedNextPage === null) {
            return $response;
        }

        if (!$response->isRedirect()) {
            throw new \RuntimeException(
                "Wizard page '{$page}' did not redirect (HTTP {$response->status}). Page said: "
                . $this->errorExcerpt($response),
            );
        }

        $location = (string) $response->location();

        if (!str_contains($location, "page_{$expectedNextPage}.php")) {
            throw new \RuntimeException(
                "Wizard page '{$page}' redirected to {$location}, expected page_{$expectedNextPage}.php. "
                . $this->errorExcerpt($this->client->follow($response)),
            );
        }

        return $response;
    }

    private function assertHealthy(string $page, Response $response): void
    {
        $directory = dirname(__DIR__, 2) . '/reports/install';
        is_dir($directory) || mkdir($directory, 0777, true);
        file_put_contents("{$directory}/{$page}.html", $response->body);

        if ($response->status >= 500) {
            throw new \RuntimeException("Wizard page '{$page}' failed with HTTP {$response->status}: " . $this->errorExcerpt($response));
        }

        if (preg_match('/(Fatal error|Parse error|Uncaught )/i', $response->body) === 1) {
            throw new \RuntimeException("Wizard page '{$page}' raised a PHP error: " . $this->errorExcerpt($response));
        }
    }

    private function errorExcerpt(Response $response): string
    {
        $found = [];

        if (preg_match_all('#<(?:div|p)[^>]*class="[^"]*(?:error|x2-note|errorMsg)[^"]*"[^>]*>(.*?)</(?:div|p)>#is', $response->body, $matches) > 0) {
            foreach ($matches[1] as $fragment) {
                $found[] = trim((string) preg_replace('/\s+/', ' ', strip_tags($fragment)));
            }
        }

        if ($found !== []) {
            return substr(implode(' | ', array_filter($found)), 0, 600);
        }

        $text = trim((string) preg_replace('/\s+/', ' ', strip_tags($response->body)));

        return substr($text, -500);
    }
}
