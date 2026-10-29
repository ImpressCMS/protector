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
    /**
     * @readonly
     */
    public string $id;
    /**
     * @readonly
     */
    public string $given;
    /**
     * @readonly
     */
    public string $when;
    /**
     * @readonly
     */
    public string $then;
    public function __construct(string $id, string $given, string $when, string $then)
    {
        $this->id = $id;
        $this->given = $given;
        $this->when = $when;
        $this->then = $then;
    }
}
