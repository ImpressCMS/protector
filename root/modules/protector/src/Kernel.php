<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector;

use ImpressCMS\Module\Protector\Ban\BanList;
use ImpressCMS\Module\Protector\Ban\GroupOneIpList;
use ImpressCMS\Module\Protector\Ban\HtaccessWriter;
use ImpressCMS\Module\Protector\Ban\IpMatch;
use ImpressCMS\Module\Protector\Ban\IpMatcher;
use ImpressCMS\Module\Protector\Config\ConfigStore;
use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Database\CorePdoProvider;
use ImpressCMS\Module\Protector\Database\DatabaseTrap;
use ImpressCMS\Module\Protector\Dos\AccessRepository;
use ImpressCMS\Module\Protector\Dos\BandwidthLimiter;
use ImpressCMS\Module\Protector\Dos\BruteForceGuard;
use ImpressCMS\Module\Protector\Dos\DosGuard;
use ImpressCMS\Module\Protector\Filter\FilterHandler;
use ImpressCMS\Module\Protector\Http\ExitResponder;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Legacy\ProtectorFacade;
use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Log\LogLevel;
use ImpressCMS\Module\Protector\Output\XssUmbrella;
use ImpressCMS\Module\Protector\Policy\ViolationPolicy;
use ImpressCMS\Module\Protector\Request\DirectoryTraversalGuard;
use ImpressCMS\Module\Protector\Request\IdValueSanitiser;
use ImpressCMS\Module\Protector\Request\IsolatedCommentGuard;
use ImpressCMS\Module\Protector\Request\LegacyFeatureGuard;
use ImpressCMS\Module\Protector\Request\ManipulationGuard;
use ImpressCMS\Module\Protector\Request\RequestMutator;
use ImpressCMS\Module\Protector\Request\RequestScanner;
use ImpressCMS\Module\Protector\Request\SpamGuard;
use ImpressCMS\Module\Protector\Request\UnionGuard;
use ImpressCMS\Module\Protector\Request\UploadGuard;
use ImpressCMS\Module\Protector\Session\SessionHijackGuard;
use ImpressCMS\Module\Protector\Session\SessionPurger;
use ImpressCMS\Module\Protector\Session\Visitor;
use ImpressCMS\Module\Protector\Storage\DataPaths;

final class Kernel
{
    private static ?self $instance = null;

    private bool $prechecked = false;

    private bool $postchecked = false;

    private bool $banPermanentlyWhenPossible = false;

    private bool $banTemporarilyWhenPossible = false;

    private ?RequestScanner $scanner = null;

    private mixed $matchedBanInfo = null;

    private function __construct(
        private readonly DataPaths $paths,
        private readonly ConfigStore $config,
        private readonly Responder $responder,
        private readonly AuditLog $log,
        private readonly FilterHandler $filters,
        private readonly BanList $banList,
        private readonly GroupOneIpList $groupOneIps,
        private readonly IpMatcher $ipMatcher,
        private readonly HtaccessWriter $htaccess,
        private readonly BandwidthLimiter $bandwidth,
        private readonly SessionPurger $purger,
        private readonly RequestMutator $mutator,
        private readonly DatabaseTrap $databaseTrap,
    ) {
    }

    public static function boot(): self
    {
        if (self::$instance === null) {
            self::$instance = self::assemble();
            class_exists(ProtectorFacade::class);
        }

        return self::$instance;
    }

    private static function assemble(): self
    {
        $paths = DataPaths::forCurrentSite();
        $config = new ConfigStore($paths);
        $responder = new ExitResponder();
        $filters = new FilterHandler($config, $responder, dirname(__DIR__));

        return new self(
            $paths,
            $config,
            $responder,
            new AuditLog($config, new CorePdoProvider(), XOOPS_DB_PREFIX),
            $filters,
            new BanList($paths),
            new GroupOneIpList($paths),
            new IpMatcher(),
            new HtaccessWriter(),
            new BandwidthLimiter($paths),
            new SessionPurger($filters, $responder),
            new RequestMutator(),
            new DatabaseTrap($config),
        );
    }

    public function precheck(): void
    {
        if ($this->prechecked) {
            return;
        }

        $this->prechecked = true;

        if (defined('_INSTALL_CHARSET') && !defined('XOOPS_MAINFILE_INCLUDED')) {
            $this->responder->halt('To use installer, remove the following plugin first: /plugins/preload/protector.php');
        }

        $config = $this->config->current();

        if (!$config->isGloballyDisabled()) {
            $this->scanner = new RequestScanner($config, $this->log, $this->mutator);
            $this->scanner->scan();
        }

        $this->refuseWhenBandwidthIsExhausted($config);
        $this->refuseBannedClient();

        if ($config->isGloballyDisabled()) {
            return;
        }

        $this->guardRequest($config, $config->isReliableIp(ServerRequest::rawClientIp()));
    }

    public function postcheck(): void
    {
        if ($this->postchecked) {
            return;
        }

        $this->postchecked = true;

        $this->warnWhenDataDirectoryIsNotWritable();
        $this->refreshConfigFromDatabase();

        $config = $this->config->current();

        if ($config->isEmpty() || $config->isGloballyDisabled()) {
            return;
        }

        $visitor = Visitor::current();

        $this->refuseGroupOneAdministratorOutsideAllowedIps($visitor);

        if ($config->isReliableIp(ServerRequest::rawClientIp())) {
            return;
        }

        $canBan = $this->canBan($visitor, $config);

        if (!$visitor->isMember() && $this->isLoginAttempt()) {
            $this->bruteForceGuard($config)->check();
        }

        $this->applyBansDecidedByPrecheck($canBan, $config);
        $this->checkRequestRate($visitor, $canBan, $config);

        (new SessionHijackGuard($config, $this->purger))->check($visitor);

        $this->checkSqlInjectionPatterns($visitor, $canBan, $config);
        $this->checkPostedContent($visitor, $config);

        if ((string) ($_SERVER['SCRIPT_FILENAME'] ?? '') === ICMS_ROOT_PATH . '/register.php') {
            $this->filters->execute('postcommon_register');
        }

        if ($config->enabled('enable_manip_check')) {
            (new ManipulationGuard($this->config, $this->filters, $this->responder))->check();
        }
    }

    public function auditLog(): AuditLog
    {
        return $this->log;
    }

    public function configStore(): ConfigStore
    {
        return $this->config;
    }

    public function responder(): Responder
    {
        return $this->responder;
    }

    public function databaseTrap(): DatabaseTrap
    {
        return $this->databaseTrap;
    }

    public function banList(): BanList
    {
        return $this->banList;
    }

    public function groupOneIps(): GroupOneIpList
    {
        return $this->groupOneIps;
    }

    public function ipMatcher(): IpMatcher
    {
        return $this->ipMatcher;
    }

    public function htaccess(): HtaccessWriter
    {
        return $this->htaccess;
    }

    public function purger(): SessionPurger
    {
        return $this->purger;
    }

    public function filters(): FilterHandler
    {
        return $this->filters;
    }

    public function paths(): DataPaths
    {
        return $this->paths;
    }

    public function matchedBanInfo(): mixed
    {
        return $this->matchedBanInfo;
    }

    public function rememberMatch(?IpMatch $match): void
    {
        $this->matchedBanInfo = $match?->info;
    }

    public function legacyFacade(): ProtectorFacade
    {
        return new ProtectorFacade($this);
    }

    private function refuseWhenBandwidthIsExhausted(ProtectorConfig $config): void
    {
        if ($config->int('bwlimit_count') < 10 || !$this->bandwidth->isLimited()) {
            return;
        }

        header('HTTP/1.0 503 Service unavailable');
        $this->filters->run('precommon_bwlimit', 'This site is very crowed now. try later.');
    }

    private function refuseBannedClient(): void
    {
        $match = $this->ipMatcher->find($this->banList->entries(), ServerRequest::clientIp());
        $this->rememberMatch($match);

        if ($match === null) {
            return;
        }

        $this->filters->run('precommon_badip', 'You are registered as BAD_IP by Protector.');
    }

    private function guardRequest(ProtectorConfig $config, bool $reliable): void
    {
        $forced = str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), 'protector/admin/index.php?page=advisory');

        if ($forced || $config->enabled('enable_dblayertrap')) {
            $this->define('PROTECTOR_ENABLED_ANTI_SQL_INJECTION');
            $this->databaseTrap->arm($forced);
        }

        if ($config->enabled('enable_bigumbrella')) {
            $this->define('PROTECTOR_ENABLED_ANTI_XSS');
            (new XssUmbrella())->start();
        }

        if ($config->enabled('id_forceintval')) {
            (new IdValueSanitiser())->apply();
        }

        if (!$reliable && $config->enabled('file_dotdot')) {
            (new DirectoryTraversalGuard($this->log))->apply();
        }

        if (!$reliable) {
            $this->refuseUnsafeUploads($config);
        }

        $this->handleContamination($config);

        if ($config->enabled('disable_features')) {
            (new LegacyFeatureGuard($config, $this->log, $this->responder))->apply();
        }
    }

    private function refuseUnsafeUploads(ProtectorConfig $config): void
    {
        if (empty($_FILES) || !$config->enabled('die_badext') || defined('PROTECTOR_SKIP_FILESCHECKER')) {
            return;
        }

        if ((new UploadGuard($this->log))->isSafe($_FILES)) {
            return;
        }

        $this->log->write($this->log->lastType());
        $this->purger->purge();
    }

    private function handleContamination(ProtectorConfig $config): void
    {
        if ($this->scanner === null || !$this->scanner->isContaminated()) {
            return;
        }

        $policy = new ViolationPolicy($config->int('contami_action'));

        if ($policy->bansTemporarily()) {
            $this->banPermanentlyWhenPossible = $policy->bansPermanently();
            $this->banTemporarilyWhenPossible = !$policy->bansPermanently();
            $_GET = $_POST = [];
        }

        if ($policy->bansTemporarily() && $policy->exits()) {
            $this->banClientNow($policy, $config);
        }

        $this->log->write($this->log->lastType());

        if ($policy->exits()) {
            $this->purger->purge();
        }
    }

    private function banClientNow(ViolationPolicy $policy, ProtectorConfig $config): void
    {
        $this->banPermanentlyWhenPossible = false;
        $this->banTemporarilyWhenPossible = false;

        $policy->bansPermanently()
            ? $this->banList->registerClient()
            : $this->banList->registerClient(time() + $config->int('banip_time0'));
    }

    private function warnWhenDataDirectoryIsNotWritable(): void
    {
        $directory = $this->paths->directory();

        if (($_SERVER['REQUEST_URI'] ?? '') === '/admin.php' && !is_writable($directory)) {
            trigger_error("You should turn the directory {$directory} writable", E_USER_WARNING);
        }
    }

    private function refreshConfigFromDatabase(): void
    {
        $connection = \Icms\Db\Factory::instance()->conn;

        if (empty($connection)) {
            return;
        }

        $this->config->refreshFromDatabase();
    }

    private function refuseGroupOneAdministratorOutsideAllowedIps(Visitor $visitor): void
    {
        if (!$visitor->isInGroup(1)) {
            return;
        }

        $allowed = $this->groupOneIps->entriesWithInfo();

        if (!implode('', array_keys($allowed))) {
            return;
        }

        $match = $this->ipMatcher->find($allowed, ServerRequest::clientIp());
        $this->rememberMatch($match);

        if ($match === null) {
            $this->responder->halt('This account is disabled for your IP by Protector.<br />Clear cookie if you want to access this site as a guest.');
        }
    }

    private function canBan(Visitor $visitor, ProtectorConfig $config): bool
    {
        if (!$visitor->isMember()) {
            return true;
        }

        return array_intersect($visitor->groups(), $config->storedList('bip_except')) === [];
    }

    private function isLoginAttempt(): bool
    {
        $submitted = !empty($_POST['uname']) && !empty($_POST['pass']);
        $remembered = !empty($_COOKIE['autologin_uname']) && !empty($_COOKIE['autologin_pass']);

        return $submitted || $remembered;
    }

    private function applyBansDecidedByPrecheck(bool $canBan, ProtectorConfig $config): void
    {
        if (!$canBan) {
            return;
        }

        if ($this->banPermanentlyWhenPossible) {
            $this->banList->registerClient();

            return;
        }

        if ($this->banTemporarilyWhenPossible) {
            $this->banList->registerClient(time() + $config->int('banip_time0'));
        }
    }

    private function checkRequestRate(Visitor $visitor, bool $canBan, ProtectorConfig $config): void
    {
        if ($this->skipsRateCheck($config)) {
            return;
        }

        $this->dosGuard($config)->check($visitor->uid(), $canBan);
    }

    private function skipsRateCheck(ProtectorConfig $config): bool
    {
        if (defined('PROTECTOR_SKIP_DOS_CHECK')) {
            return true;
        }

        $skipped = explode('|', $config->string('dos_skipmodules'));
        $module = $GLOBALS['xoopsModule'] ?? null;

        if (is_object($module)) {
            return in_array($module->getVar('dirname'), $skipped);
        }

        foreach ($skipped as $directory) {
            if ($directory && strstr((string) getcwd(), $directory)) {
                return true;
            }
        }

        return false;
    }

    private function checkSqlInjectionPatterns(Visitor $visitor, bool $canBan, ProtectorConfig $config): void
    {
        $doubtful = $this->scanner?->doubtfulValues() ?? [];

        $comment = new ViolationPolicy($config->int('isocom_action'));

        if (!(new IsolatedCommentGuard($this->log, $this->mutator))->isSafe($doubtful, $comment->sanitizes())) {
            $this->punish($comment, 'ISOCOM', $visitor->uid(), $canBan, $config);
        }

        $union = new ViolationPolicy($config->int('union_action'));

        if (!(new UnionGuard($this->log, $this->mutator))->isSafe($doubtful, $union->sanitizes())) {
            $this->punish($union, 'UNION', $visitor->uid(), $canBan, $config);
        }
    }

    private function punish(ViolationPolicy $policy, string $type, int $uid, bool $canBan, ProtectorConfig $config): void
    {
        if ($canBan && $policy->bansPermanently()) {
            $this->banList->registerClient();
        }

        if ($canBan && !$policy->bansPermanently() && $policy->bansTemporarily()) {
            $this->banList->registerClient(time() + $config->int('banip_time0'));
        }

        $this->log->write($type, $uid, true, LogLevel::Injection);

        if ($policy->exits()) {
            $this->purger->purge();
        }
    }

    private function checkPostedContent(Visitor $visitor, ProtectorConfig $config): void
    {
        if (empty($_POST)) {
            return;
        }

        $spam = new SpamGuard($this->log, $this->filters, $this->responder);

        if ($visitor->isMember() && !$visitor->isAdmin() && $config->enabled('spamcount_uri4user')) {
            $spam->check($config->int('spamcount_uri4user'), $visitor->uid());
        }

        if (!$visitor->isMember() && $config->enabled('spamcount_uri4guest')) {
            $spam->check($config->int('spamcount_uri4guest'), 0);
        }

        $this->filters->execute('postcommon_post');
    }

    private function dosGuard(ProtectorConfig $config): DosGuard
    {
        return new DosGuard(
            $config,
            new AccessRepository(),
            $this->bandwidth,
            $this->banList,
            $this->htaccess,
            $this->filters,
            $this->log,
            $this->responder,
        );
    }

    private function bruteForceGuard(ProtectorConfig $config): BruteForceGuard
    {
        return new BruteForceGuard($config, new AccessRepository(), $this->banList, $this->filters, $this->log, $this->responder);
    }

    private function define(string $constant): void
    {
        if (!defined($constant)) {
            define($constant, 1);
        }
    }
}
