<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Tests\Functional\Http;

final class Client
{
    private string $cookieFile;

    private bool $ownsCookieFile = true;

    private array $defaultHeaders = [];

    public function __construct(
        private readonly string $baseUrl,
        private readonly ?string $sourceIp = null,
        private readonly ?string $userAgent = null,
    ) {
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'pft_cookie_');
    }

    public function __destruct()
    {
        if ($this->ownsCookieFile) {
            @unlink($this->cookieFile);
        }
    }

    /**
     * The same browser (shared cookies) arriving from another network address.
     */
    public function withSourceIp(string $sourceIp): self
    {
        $clone = new self($this->baseUrl, $sourceIp, $this->userAgent);
        @unlink($clone->cookieFile);
        $clone->cookieFile = $this->cookieFile;
        $clone->ownsCookieFile = false;
        $clone->defaultHeaders = $this->defaultHeaders;

        return $clone;
    }

    public function withHeader(string $name, string $value): void
    {
        $this->defaultHeaders[$name] = $value;
    }

    public function get(string $pathOrUrl, array $query = [], array $headers = []): Response
    {
        $url = $this->absolute($pathOrUrl);

        if ($query !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        return $this->send('GET', $url, null, $headers);
    }

    /**
     * @param array<string, mixed> $fields use \CURLFile values for uploads
     */
    public function post(string $pathOrUrl, array $fields = [], array $headers = [], bool $multipart = false): Response
    {
        $hasFile = false;

        foreach ($fields as $value) {
            if ($value instanceof \CURLFile) {
                $hasFile = true;
            }
        }

        $body = ($hasFile || $multipart) ? $this->flatten($fields) : http_build_query($fields);

        return $this->send('POST', $this->absolute($pathOrUrl), $body, $this->withReferer($headers));
    }

    public function postRaw(string $pathOrUrl, string $body, array $headers = []): Response
    {
        return $this->send('POST', $this->absolute($pathOrUrl), $body, $this->withReferer($headers));
    }

    public function follow(Response $response, int $maxHops = 8): Response
    {
        $current = $response;

        for ($hop = 0; $hop < $maxHops; $hop++) {
            if (!$current->isRedirect()) {
                return $current;
            }

            $current = $this->get($this->resolve($current->url, (string) $current->location()));
        }

        throw new \RuntimeException("Too many redirects starting from {$response->url}");
    }

    /**
     * The core puts the database layer into write-protected "proxy" mode for any POST without a same-site
     * Referer (Icms\Core\Security::service), exactly as a browser submitting a form would always send one.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function withReferer(array $headers): array
    {
        $hasReferer = false;

        foreach (array_keys($headers + $this->defaultHeaders) as $name) {
            $hasReferer = $hasReferer || strtolower((string) $name) === 'referer';
        }

        return $hasReferer ? $headers : $headers + ['Referer' => rtrim($this->baseUrl, '/') . '/'];
    }

    public function cookies(): array
    {
        $cookies = [];

        foreach (file($this->cookieFile, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', $line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) === 7) {
                $cookies[$parts[5]] = $parts[6];
            }
        }

        return $cookies;
    }

    public function absolute(string $pathOrUrl): string
    {
        if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($pathOrUrl, '/');
    }

    public function resolve(string $from, string $location): string
    {
        $location = (string) preg_replace('#\\\\+/#', '/', $location);

        if (preg_match('#^https?://#i', $location) === 1) {
            return $location;
        }

        $parts = parse_url($from);
        $origin = "{$parts['scheme']}://{$parts['host']}" . (isset($parts['port']) ? ":{$parts['port']}" : '');

        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }

        $directory = rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/');

        return "{$origin}{$directory}/{$location}";
    }

    private function flatten(array $fields, string $prefix = ''): array
    {
        $flat = [];

        foreach ($fields as $key => $value) {
            $name = $prefix === '' ? (string) $key : "{$prefix}[{$key}]";

            if (is_array($value)) {
                $flat += $this->flatten($value, $name);

                continue;
            }

            $flat[$name] = $value;
        }

        return $flat;
    }

    private function send(string $method, string $url, array|string|null $body, array $headers): Response
    {
        $handle = curl_init($url);
        $responseHeaders = [];

        $allHeaders = $this->defaultHeaders + $headers;
        $headerLines = [];

        foreach ($allHeaders as $name => $value) {
            $headerLines[] = "{$name}: {$value}";
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $length = strlen($line);

                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $responseHeaders[strtolower(trim($name))] = trim($value);
                }

                return $length;
            },
        ]);

        if ($this->sourceIp !== null) {
            curl_setopt($handle, CURLOPT_INTERFACE, $this->sourceIp);
        }

        if ($this->userAgent !== null) {
            curl_setopt($handle, CURLOPT_USERAGENT, $this->userAgent);
        }

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $content = curl_exec($handle);

        if ($content === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new \RuntimeException("HTTP {$method} {$url} failed: {$error}");
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return new Response($status, $responseHeaders, (string) $content, $url);
    }
}
