<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Request;

final class DoubtfulValue
{
    /**
     * @readonly
     */
    public string $source;
    /**
     * @var array<int, int|string>
     * @readonly
     */
    public array $path;
    /**
     * @readonly
     */
    public string $value;
    /** @param array<int, int|string> $path */
    public function __construct(string $source, array $path, string $value)
    {
        $this->source = $source;
        $this->path = $path;
        $this->value = $value;
    }
}
