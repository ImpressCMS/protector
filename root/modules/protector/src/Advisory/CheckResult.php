<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Advisory;

final class CheckResult
{
    /**
     * @readonly
     */
    public string $subject;
    /**
     * @readonly
     */
    public string $value;
    /**
     * @readonly
     */
    public bool $ok;
    /**
     * @readonly
     */
    public string $statusText;
    /**
     * @readonly
     */
    public string $advice = '';
    /**
     * @readonly
     */
    public ?string $imageUrl = null;
    /**
     * @readonly
     */
    public ?string $linkUrl = null;
    /**
     * @readonly
     */
    public string $linkText = '';
    public function __construct(string $subject, string $value, bool $ok, string $statusText, string $advice = '', ?string $imageUrl = null, ?string $linkUrl = null, string $linkText = '')
    {
        $this->subject = $subject;
        $this->value = $value;
        $this->ok = $ok;
        $this->statusText = $statusText;
        $this->advice = $advice;
        $this->imageUrl = $imageUrl;
        $this->linkUrl = $linkUrl;
        $this->linkText = $linkText;
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
