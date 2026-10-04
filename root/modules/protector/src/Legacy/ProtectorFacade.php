<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Legacy;

use ImpressCMS\Module\Protector\Http\ServerRequest;
use ImpressCMS\Module\Protector\Kernel;

/**
 * The global "Protector" class that third-party filters (filters_byconfig/) still use. New code must not.
 *
 * @property array<string, mixed> $_conf
 * @property string $message
 * @property string $last_error_type
 * @property mixed $ip_matched_info
 */
final class ProtectorFacade
{
    public string $mydirname = 'protector';

    public function __construct(private readonly Kernel $kernel)
    {
    }

    public static function &getInstance(): self
    {
        $facade = Kernel::boot()->legacyFacade();

        return $facade;
    }

    public function __get(string $name): mixed
    {
        return match ($name) {
            '_conf' => $this->getConf(),
            'message' => $this->kernel->auditLog()->message(),
            'last_error_type' => $this->kernel->auditLog()->lastType(),
            'ip_matched_info' => $this->kernel->matchedBanInfo(),
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['_conf', 'message', 'last_error_type', 'ip_matched_info'], true) && $this->__get($name) !== null;
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'message') {
            $this->kernel->auditLog()->replaceMessage((string) $value);

            return;
        }

        if ($name === 'last_error_type') {
            $this->kernel->auditLog()->rememberType((string) $value);
        }
    }

    /** @return array<string, mixed> */
    public function getConf(): array
    {
        return $this->kernel->configStore()->current()->toArray();
    }

    public function output_log(string $type = 'UNKNOWN', int $uid = 0, bool $unique_check = false, int $level = 1): bool
    {
        $this->kernel->auditLog()->write($type, $uid, $unique_check, $level);

        return true;
    }

    public function register_bad_ips(int $jailed_time = 0, ?string $ip = null): bool
    {
        $bans = $this->kernel->banList();

        return empty($ip) ? $bans->registerClient($jailed_time) : $bans->register($ip, $jailed_time);
    }

    /** @return array<int|string, mixed> */
    public function get_bad_ips(bool $with_jailed_time = false): array
    {
        $bans = $this->kernel->banList();

        return $with_jailed_time ? $bans->entries() : $bans->addresses();
    }

    /** @param array<string, int> $bad_ips */
    public function write_file_badips(array $bad_ips): bool
    {
        return $this->kernel->banList()->write($bad_ips);
    }

    /** @return array<int|string, mixed> */
    public function get_group1_ips(bool $with_info = false): array
    {
        $list = $this->kernel->groupOneIps();

        return $with_info ? $list->entriesWithInfo() : $list->entries();
    }

    /** @param array<int|string, mixed> $ips */
    public function ip_match(array $ips): bool
    {
        $match = $this->kernel->ipMatcher()->find($ips, ServerRequest::clientIp());
        $this->kernel->rememberMatch($match);

        return $match !== null;
    }

    public function deny_by_htaccess(?string $ip = null): bool
    {
        $htaccess = $this->kernel->htaccess();

        return empty($ip) ? $htaccess->denyClient() : $htaccess->deny($ip);
    }

    public function purge(bool $redirect_to_top = false): void
    {
        $this->kernel->purger()->purge($redirect_to_top);
    }

    public function call_filter(string $type, string $dying_message = ''): int
    {
        return $this->kernel->filters()->run($type, $dying_message);
    }

    public function get_filepath4badips(): string
    {
        return $this->kernel->paths()->badIps();
    }

    public function get_filepath4group1ips(): string
    {
        return $this->kernel->paths()->groupOneIps();
    }

    public function get_filepath4bwlimit(): string
    {
        return $this->kernel->paths()->bandwidthLimit();
    }

    public function get_filepath4confighcache(): string
    {
        return $this->kernel->paths()->configCache();
    }
}

class_alias(ProtectorFacade::class, 'Protector');
