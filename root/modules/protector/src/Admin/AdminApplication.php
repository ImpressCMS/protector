<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

use ImpressCMS\Module\Protector\Advisory\DatabaseLayerCheck;
use ImpressCMS\Module\Protector\Advisory\DatabasePrefixCheck;
use ImpressCMS\Module\Protector\Advisory\DataDirectoryCheck;
use ImpressCMS\Module\Protector\Advisory\IniFlagCheck;
use ImpressCMS\Module\Protector\Advisory\PhpVersionCheck;
use ImpressCMS\Module\Protector\Advisory\PreloadCheck;
use ImpressCMS\Module\Protector\Advisory\StartupHooksCheck;
use ImpressCMS\Module\Protector\Advisory\TrustPathCheck;
use ImpressCMS\Module\Protector\Kernel;
use ImpressCMS\Module\Protector\Log\LogRepository;

final class AdminApplication
{
    public function __construct(
        private readonly StartPage $startPage,
        private readonly AdvisoryPage $advisoryPage,
    ) {
    }

    public static function create(Kernel $kernel): self
    {
        $database = $kernel->pdoProvider();
        $home = ICMS_URL . '/modules/protector/admin/index.php';

        $startPage = new StartPage(
            new CoreCsrfTokens(),
            new CoreRedirector(),
            new LogRepository($database, XOOPS_DB_PREFIX),
            $kernel->banList(),
            $kernel->groupOneIps(),
            new IpListParser(),
            [
                'ipsUpdated' => _AM_MSG_IPFILESUPDATED,
                'badIpsCannotOpen' => _AM_MSG_BADIPSCANTOPEN,
                'groupOneCannotOpen' => _AM_MSG_GROUP1IPSCANTOPEN,
                'removed' => _AM_MSG_REMOVED,
                'invalidToken' => _AM_MSG_INVALIDTOKEN,
                'guests' => _GUESTS,
            ],
            static fn (int $timestamp): string => formatTimestamp($timestamp),
            static fn (int $total, int $size, int $offset): string => (new \Icms\View\PageNav($total, $size, $offset, 'pos', "num={$size}"))->renderNav(10),
            $home,
            $kernel->paths()->directory(),
            ICMS_TRUST_PATH,
        );

        return new self($startPage, new AdvisoryPage(self::advisoryChecks($kernel), ICMS_URL));
    }

    /** @param array<string, mixed> $post */
    public function handle(array $post): void
    {
        $this->startPage->handle($post);
    }

    /**
     * @param array<string, mixed> $query
     * @return array{template: string, variables: array<string, mixed>}
     */
    public function page(string $name, array $query): array
    {
        if ($name === 'advisory') {
            return ['template' => 'db:protector_admin_advisory.html', 'variables' => $this->advisoryPage->variables()];
        }

        return ['template' => 'db:protector_admin_index.html', 'variables' => $this->startPage->variables($query)];
    }

    /** @return list<\ImpressCMS\Module\Protector\Advisory\Check> */
    private static function advisoryChecks(Kernel $kernel): array
    {
        return [
            new TrustPathCheck(ICMS_ROOT_PATH, ICMS_TRUST_PATH, ICMS_URL, _AM_ADV_TRUSTPATHPUBLIC, _AM_ADV_TRUSTPATHPUBLICLINK),
            new IniFlagCheck('allow_url_fopen', (bool) ini_get('allow_url_fopen'), _AM_ADV_NOTSECURE, _AM_ADV_ALLOWURLFOPEN),
            new IniFlagCheck('session.use_trans_sid', (bool) ini_get('session.use_trans_sid'), _AM_ADV_NOTSECURE, _AM_ADV_USETRANSSID),
            new DatabasePrefixCheck(XOOPS_DB_PREFIX, _AM_ADV_NOTSECURE, _AM_ADV_DBPREFIX),
            new StartupHooksCheck(defined('PROTECTOR_PRECHECK_INCLUDED'), defined('PROTECTOR_POSTCHECK_INCLUDED'), _AM_ADV_NOTSECURE, _AM_ADV_MAINUNPATCHED),
            new DatabaseLayerCheck(get_class(\Icms\Db\Factory::instance()), XOOPS_DB_TYPE, _AM_ADV_DBFACTORYPATCHED, _AM_ADV_DBFACTORYUNPATCHED),
            new PreloadCheck(ICMS_PRELOAD_PATH, _AM_ADV_NOTSECURE, _AM_ADV_PRELOADMISSING),
            new DataDirectoryCheck($kernel->paths()->directory(), ICMS_TRUST_PATH, _AM_ADV_NOTSECURE, _AM_ADV_DATADIRECTORY),
            new PhpVersionCheck(PHP_VERSION, _AM_ADV_NOTSECURE, _AM_ADV_PHPVERSION),
        ];
    }
}
