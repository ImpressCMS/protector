<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Log\AuditLog;

final class IsolatedCommentGuard
{
    public function __construct(
        private readonly AuditLog $log,
        private readonly RequestMutator $mutator,
    ) {
    }

    /** @param array<int, DoubtfulValue> $doubtful */
    public function isSafe(array $doubtful, bool $sanitize): bool
    {
        $safe = true;

        foreach ($doubtful as $request) {
            if (!$this->hasUnclosedComment($request->value)) {
                continue;
            }

            $this->log->noteIncident('ISOCOM', "Isolated comment-in found. ({$request->value})\n");

            if ($sanitize) {
                $this->mutator->replace($request->source, $request->path, $request->value . '*/');
            }

            $safe = false;
        }

        return $safe;
    }

    private function hasUnclosedComment(string $value): bool
    {
        $rest = $value;

        while (($rest = strstr($rest, '/*')) !== false) {
            $rest = strstr(substr($rest, 2), '*/');

            if ($rest === false) {
                return true;
            }
        }

        return false;
    }
}
