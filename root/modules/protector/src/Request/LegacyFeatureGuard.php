<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Config\ProtectorConfig;
use ImpressCMS\Module\Protector\Http\Responder;
use ImpressCMS\Module\Protector\Log\AuditLog;

final class LegacyFeatureGuard
{
    private const BLOCK_XMLRPC_AND_CRITERIA_BUG = 1;

    private const BLOCK_OLD_XOOPS_EXPLOITS = 1024;

    public function __construct(
        private readonly ProtectorConfig $config,
        private readonly AuditLog $log,
        private readonly Responder $responder,
    ) {
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
        if (str_ends_with($this->script(), 'xmlrpc.php')) {
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

        $isSystemAdmin = str_ends_with($script, 'modules/system/admin.php');

        if ($isSystemAdmin && $this->requestHas('fct', 'findusers')) {
            $this->blockQuotesInPostedFields();
        }

        if (isset($_POST['com_dopreview']) && !str_contains(substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), -16), 'comment_post.php')) {
            $_POST['dohtml'] = 0;
        }

        if ($isSystemAdmin && $this->requestHas('fct', 'tplsets') && $this->isTemplatePreview()) {
            $this->responder->halt("Danger! don't use this preview.(by Protector)");
        }
    }

    private function blockMiscAndEditUser(string $script): void
    {
        $isMisc = str_ends_with($script, 'misc.php');

        if ($isMisc && $this->requestHas('type', 'debug') && !preg_match('/^dummy_[0-9]+\.html$/', (string) ($_GET['file'] ?? ''))) {
            $this->logAndHalt('misc debug');
        }

        if ($isMisc && $this->requestHas('type', 'smilies') && !preg_match('/^[0-9a-z_]*$/i', (string) ($_GET['target'] ?? ''))) {
            $this->logAndHalt('misc smilies');
        }

        $avatar = (string) ($_POST['user_avatar'] ?? '');

        if (str_ends_with($script, 'edituser.php') && ($_POST['op'] ?? null) === 'avatarchoose' && str_contains($avatar, '..')) {
            $this->logAndHalt('edituser avatarchoose');
        }
    }

    private function blockQuotesInPostedFields(): void
    {
        foreach ($_POST as $key => $value) {
            if (str_contains((string) $key, "'") || (is_string($value) && str_contains($value, "'"))) {
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

    private function logAndHalt(string $type, bool $skipRepeat = false): never
    {
        $this->log->write($type, 0, $skipRepeat);
        $this->responder->halt();
    }
}
