<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Ban;

use ImpressCMS\Module\Protector\Http\ServerRequest;

final class HtaccessWriter
{
    public function denyClient(): bool
    {
        return $this->deny(ServerRequest::clientIp());
    }

    public function deny(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $target = ICMS_ROOT_PATH . '/.htaccess';
        $backup = ICMS_ROOT_PATH . '/uploads/.htaccess.bak';

        $body = file_get_contents($target);

        if ($body && !file_exists($backup)) {
            file_put_contents($backup, $body);
        }

        if (!$body && file_exists($backup)) {
            $body = file_get_contents($backup);
        }

        if ($body === false) {
            $body = '';
        }

        if (!preg_match("/^(.*)#PROTECTOR#\s+(DENY FROM .*)\n#PROTECTOR#\n(.*)$/si", $body, $parts)) {
            return $this->write("#PROTECTOR#\nDENY FROM {$ip}\n#PROTECTOR#\n{$body}");
        }

        if (substr($parts[2], -strlen($ip)) === $ip) {
            return true;
        }

        return $this->write("{$parts[1]}#PROTECTOR#\n{$parts[2]} {$ip}\n#PROTECTOR#\n{$parts[3]}");
    }

    private function write(string $body): bool
    {
        $handle = fopen(ICMS_ROOT_PATH . '/.htaccess', 'w');

        if (!$handle) {
            return false;
        }

        @flock($handle, LOCK_EX);
        fwrite($handle, $body);
        @flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }
}
