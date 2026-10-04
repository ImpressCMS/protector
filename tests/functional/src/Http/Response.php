<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Http;

final class Response
{
    /**
     * @param array<string, string> $headers lower-cased header name => last value
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $url,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function location(): ?string
    {
        return $this->header('location');
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->location() !== null;
    }

    public function contains(string $needle): bool
    {
        return str_contains($this->body, $needle);
    }

    public function json(): array
    {
        $start = strpos($this->body, '{');
        $end = strrpos($this->body, '}');
        $json = $start === false || $end === false ? $this->body : substr($this->body, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("Response from {$this->url} is not JSON: " . substr($this->body, 0, 300));
        }

        return $decoded;
    }
}
