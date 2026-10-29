<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

final class IpMatch
{
    /**
     * @readonly
     */
    public string $pattern;
    /**
     * @readonly
     * @var mixed
     */
    public $info;
    /**
     * @param mixed $info
     */
    public function __construct(string $pattern, $info)
    {
        $this->pattern = $pattern;
        $this->info = $info;
    }
}
