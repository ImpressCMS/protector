<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Http;

final class Response
{
    /**
     * @readonly
     */
    public int $status;
    /**
     * @var array<string, string>
     * @readonly
     */
    public array $headers;
    /**
     * @readonly
     */
    public string $body;
    /**
     * @readonly
     */
    public string $url;
    /**
     * @param array<string, string> $headers lower-cased header name => last value
     */
    public function __construct(int $status, array $headers, string $body, string $url)
    {
        $this->status = $status;
        $this->headers = $headers;
        $this->body = $body;
        $this->url = $url;
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
        return strpos($this->body, $needle) !== false;
    }

    public function json(): array
    {
        $start = strpos($this->body, '{');
        $end = strrpos($this->body, '}');
        $json = $start === false || $end === false ? $this->body : (string) substr($this->body, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("Response from {$this->url} is not JSON: " . substr($this->body, 0, 300));
        }

        return $decoded;
    }
}
