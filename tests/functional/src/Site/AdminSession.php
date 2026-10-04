<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Site;

use ImpressCMS\Module\Protector\Tests\Functional\Config;
use ImpressCMS\Module\Protector\Tests\Functional\Http\Client;
use ImpressCMS\Module\Protector\Tests\Functional\Http\Response;

/**
 * A logged-in administrator in the control panel, used for lifecycle steps and admin-page scenarios.
 */
final class AdminSession
{
    private Client $client;

    public function __construct(private readonly Config $config, ?string $sourceIp = null)
    {
        $this->client = new Client($config->get('SITE_URL'), $sourceIp);
    }

    public function client(): Client
    {
        return $this->client;
    }

    public function login(): Response
    {
        $response = $this->client->follow($this->client->post('/user.php', [
            'uname' => $this->config->get('ADMIN_LOGIN'),
            'pass' => $this->config->adminPassword(),
            'op' => 'login',
            'xoops_redirect' => '/',
        ]));

        if ($this->client->cookies() === []) {
            throw new \RuntimeException('Admin login did not establish a session.');
        }

        return $response;
    }

    public function page(string $path, array $query = []): Response
    {
        return $this->client->follow($this->client->get($path, $query));
    }

    /**
     * Submits the first form on a page containing $marker, carrying its hidden fields (including the CSRF token).
     *
     * @param array<string, mixed> $overrides
     */
    public function submitForm(Response $page, string $marker, array $overrides = [], ?string $action = null): Response
    {
        $form = self::extractForm($page->body, $marker);
        $fields = $overrides + $form['fields'];
        $target = $action ?? $this->client->resolve($page->url, $form['action'] === '' ? $page->url : $form['action']);

        return $this->client->follow($this->client->post($target, $fields));
    }

    public function installModule(string $dirname): Response
    {
        $confirm = $this->page('/modules/system/admin.php', ['fct' => 'modulesadmin', 'op' => 'install', 'module' => $dirname]);

        if (str_contains($confirm->body, 'update_ok')) {
            $this->submitForm($confirm, 'update_ok');
            $confirm = $this->page('/modules/system/admin.php', ['fct' => 'modulesadmin', 'op' => 'install', 'module' => $dirname]);
        }

        return $this->submitForm($confirm, 'install_ok');
    }

    public function uninstallModule(string $dirname): Response
    {
        $confirm = $this->page('/modules/system/admin.php', ['fct' => 'modulesadmin', 'op' => 'uninstall', 'module' => $dirname]);

        return $this->submitForm($confirm, 'uninstall_ok');
    }

    public function updateModule(string $dirname): Response
    {
        $confirm = $this->page('/modules/system/admin.php', ['fct' => 'modulesadmin', 'op' => 'update', 'module' => $dirname]);

        return $this->submitForm($confirm, 'update_ok');
    }

    /**
     * @return array{action: string, fields: array<string, string>}
     */
    public static function extractForm(string $html, string $marker): array
    {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="utf-8"?>' . $html);

        foreach ($document->getElementsByTagName('form') as $form) {
            if (!str_contains($document->saveHTML($form) ?: '', $marker)) {
                continue;
            }

            $fields = [];

            foreach ($form->getElementsByTagName('input') as $input) {
                $name = $input->getAttribute('name');
                $type = strtolower($input->getAttribute('type'));

                if ($name === '' || in_array($type, ['submit', 'button', 'image', 'file'], true)) {
                    continue;
                }

                if (in_array($type, ['checkbox', 'radio'], true) && !$input->hasAttribute('checked')) {
                    continue;
                }

                $fields[$name] = $input->getAttribute('value');
            }

            return ['action' => $form->getAttribute('action'), 'fields' => $fields];
        }

        throw new \RuntimeException("No form containing '{$marker}' found in page.");
    }
}
