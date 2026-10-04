<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional;

/**
 * Human-readable description of what a test pins down. bin/spec.php turns these into tests/functional/SPEC.md,
 * so the specification you review is generated from the very tests that run.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class Scenario
{
    public function __construct(
        public readonly string $id,
        public readonly string $given,
        public readonly string $when,
        public readonly string $then,
    ) {
    }
}
