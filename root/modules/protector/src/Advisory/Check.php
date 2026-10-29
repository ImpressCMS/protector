<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

interface Check
{
    public function run(): CheckResult;
}
