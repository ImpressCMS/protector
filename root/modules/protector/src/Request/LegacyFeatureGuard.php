<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Log\AuditLog;

final class LegacyFeatureGuard
{
    /**
     * @readonly
     */
    private ProtectorConfig $config;
    /**
     * @readonly
     */
    private AuditLog $log;
    /**
     * @readonly
     */
    private Responder $responder;
    private const BLOCK_XMLRPC_AND_CRITERIA_BUG = 1;

    private const BLOCK_OLD_XOOPS_EXPLOITS = 1024;

    public function __construct(ProtectorConfig $config, AuditLog $log, Responder $responder)
    {
        $this->config = $config;
        $this->log = $log;
        $this->responder = $responder;
    }

    public function apply(): void
    {
        $previousLevel = error_reporting(0);
        $features = $this->config->int('disable_features');

        if ($features & self::BLOCK_XMLRPC_AND_CRITERIA_BUG) {
            $this->blockXmlRpcAndCriteriaBug();
        }

        if ($features & self::BLOCK_OLD_XOOPS_EXPLOITS) {
            $this->blockOldXoopsExploits();
        }

        error_reporting($previousLevel);
    }

    private function blockXmlRpcAndCriteriaBug(): void
    {
        if (substr_compare($this->script(), 'xmlrpc.php', -strlen('xmlrpc.php')) === 0) {
            $this->logAndHalt('xmlrpc', true);
        }

        if (($_POST['uname'] ?? null) === '0' || ($_COOKIE['autologin_pass'] ?? null) === '0') {
            $this->logAndHalt('CRITERIA');
        }
    }

    private function blockOldXoopsExploits(): void
    {
        $script = $this->script();

        if (!stristr($script, 'modules')) {
            $this->blockMiscAndEditUser($script);
        }

        $isSystemAdmin = substr_compare($script, 'modules/system/admin.php', -strlen('modules/system/admin.php')) === 0;

        if ($isSystemAdmin && $this->requestHas('fct', 'findusers')) {
            $this->blockQuotesInPostedFields();
        }

        if (isset($_POST['com_dopreview']) && strpos(substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), -16), 'comment_post.php') === false) {
            $_POST['dohtml'] = 0;
        }

        if ($isSystemAdmin && $this->requestHas('fct', 'tplsets') && $this->isTemplatePreview()) {
            $this->responder->halt("Danger! don't use this preview.(by Protector)");
        }
    }

    private function blockMiscAndEditUser(string $script): void
    {
        $isMisc = substr_compare($script, 'misc.php', -strlen('misc.php')) === 0;

        if ($isMisc && $this->requestHas('type', 'debug') && !preg_match('/^dummy_[0-9]+\.html$/', (string) ($_GET['file'] ?? ''))) {
            $this->logAndHalt('misc debug');
        }

        if ($isMisc && $this->requestHas('type', 'smilies') && !preg_match('/^[0-9a-z_]*$/i', (string) ($_GET['target'] ?? ''))) {
            $this->logAndHalt('misc smilies');
        }

        $avatar = (string) ($_POST['user_avatar'] ?? '');

        if (substr_compare($script, 'edituser.php', -strlen('edituser.php')) === 0 && ($_POST['op'] ?? null) === 'avatarchoose' && strpos($avatar, '..') !== false) {
            $this->logAndHalt('edituser avatarchoose');
        }
    }

    private function blockQuotesInPostedFields(): void
    {
        foreach ($_POST as $key => $value) {
            if (strpos((string) $key, "'") !== false || (is_string($value) && strpos($value, "'") !== false)) {
                $this->logAndHalt('findusers');
            }
        }
    }

    private function isTemplatePreview(): bool
    {
        return ($_POST['op'] ?? null) === 'previewpopup'
            || ($_GET['op'] ?? null) === 'previewpopup'
            || isset($_POST['previewtpl']);
    }

    private function requestHas(string $name, string $expected): bool
    {
        return ($_GET[$name] ?? null) === $expected || ($_POST[$name] ?? null) === $expected;
    }

    private function script(): string
    {
        return (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    }

    /**
     * @return never
     */
    private function logAndHalt(string $type, bool $skipRepeat = false)
    {
        $this->log->write($type, 0, $skipRepeat);
        $this->responder->halt();
    }
}
