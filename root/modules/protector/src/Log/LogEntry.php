<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Log;

final class LogEntry
{
    /**
     * @readonly
     */
    public int $id;
    /**
     * @readonly
     */
    public int $uid;
    /**
     * @readonly
     */
    public string $ip;
    /**
     * @readonly
     */
    public string $agent;
    /**
     * @readonly
     */
    public string $type;
    /**
     * @readonly
     */
    public string $description;
    /**
     * @readonly
     */
    public int $timestamp;
    /**
     * @readonly
     */
    public ?string $userName;
    public function __construct(int $id, int $uid, string $ip, string $agent, string $type, string $description, int $timestamp, ?string $userName)
    {
        $this->id = $id;
        $this->uid = $uid;
        $this->ip = $ip;
        $this->agent = $agent;
        $this->type = $type;
        $this->description = $description;
        $this->timestamp = $timestamp;
        $this->userName = $userName;
    }
}
