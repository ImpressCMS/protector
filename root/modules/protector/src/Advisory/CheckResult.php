<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final readonly class CheckResult
{
    public function __construct(
        public string $subject,
        public string $value,
        public bool $ok,
        public string $statusText,
        public string $advice = '',
        public ?string $imageUrl = null,
        public ?string $linkUrl = null,
        public string $linkText = '',
    ) {
    }

    public static function ok(string $subject, string $value = ''): self
    {
        return new self($subject, $value, true, 'ok');
    }

    public static function problem(string $subject, string $value, string $statusText, string $advice = ''): self
    {
        return new self($subject, $value, false, $statusText, $advice);
    }
}
