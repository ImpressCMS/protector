<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

/**
 * Marks a test that asserts TODAY's behaviour although that behaviour is a defect (see the plan, D1-D11 and S7/S11).
 * These are the only assertions allowed to change later, and only in the phase that fixes the defect.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class KnownDefect
{
    public function __construct(
        public readonly string $defect,
        public readonly string $note,
    ) {
    }
}
