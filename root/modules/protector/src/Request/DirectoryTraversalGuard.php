<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Log\AuditLog;
use ImpressCMS\Module\Protector\Log\LogLevel;

final class DirectoryTraversalGuard
{
    public function __construct(private readonly AuditLog $log)
    {
    }

    public function apply(): void
    {
        foreach ($_GET as $key => $value) {
            if (is_array($value) || !$this->looksLikeTraversal((string) $value)) {
                continue;
            }

            $value = (string) $value;
            $this->log->noteIncident('DirTraversal', "Directory Traversal '{$value}' found.\n");
            $this->log->write('DirTraversal', 0, false, LogLevel::Traversal);

            $sanitised = str_replace("\0", '', $value);

            if (substr($sanitised, -2) !== ' .') {
                $sanitised .= ' .';
            }

            $_GET[$key] = $sanitised;

            if (($_REQUEST[$key] ?? null) == $_GET[$key]) {
                $_REQUEST[$key] = $sanitised;
            }
        }
    }

    private function looksLikeTraversal(string $value): bool
    {
        return str_starts_with(trim($value), '../') || str_contains($value, '../../');
    }
}
