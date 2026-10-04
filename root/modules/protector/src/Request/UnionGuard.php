<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

use ImpressCMS\Module\Protector\Log\AuditLog;

final class UnionGuard
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
            $withoutComments = str_replace(['/*', '*/'], '', (string) preg_replace('?/\*.+\*/?sU', '', $request->value));

            if (!preg_match('/\sUNION\s+(ALL|SELECT)/i', $withoutComments)) {
                continue;
            }

            $this->log->noteIncident('UNION', "Pattern like SQL injection found. ({$request->value})\n");

            if ($sanitize) {
                $this->mutator->replace($request->source, $request->path, (string) preg_replace('/union/i', 'uni-on', $request->value));
            }

            $safe = false;
        }

        return $safe;
    }
}
