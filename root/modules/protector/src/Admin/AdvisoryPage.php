<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Admin;

use ImpressCMS\Module\Protector\Advisory\Check;

final class AdvisoryPage
{
    /**
     * @var list<Check>
     * @readonly
     */
    private array $checks;
    /**
     * @readonly
     */
    private string $siteUrl;
    /** @param list<Check> $checks */
    public function __construct(array $checks, string $siteUrl)
    {
        $this->checks = $checks;
        $this->siteUrl = $siteUrl;
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
