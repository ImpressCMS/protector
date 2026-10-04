<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

use ImpressCMS\Module\Protector\Advisory\Check;

final class AdvisoryPage
{
    /** @param list<Check> $checks */
    public function __construct(
        private readonly array $checks,
        private readonly string $siteUrl,
    ) {
    }

    /** @return array<string, mixed> */
    public function variables(): array
    {
        return [
            'results' => array_map(static fn (Check $check): array => get_object_vars($check->run()), $this->checks),
            'contaminationUrl' => "{$this->siteUrl}/index.php?xoopsConfig%5Bnocommon%5D=1",
            'isolatedCommentUrl' => "{$this->siteUrl}/index.php?cid=" . urlencode(',password /*'),
        ];
    }
}
