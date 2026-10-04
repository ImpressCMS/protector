<?php

declare(strict_types=1);

namespace ImpressCMS\Module\Protector\Session;

final class Visitor
{
    public function __construct(private readonly ?object $user)
    {
    }

    public static function current(): self
    {
        $user = \icms::$user ?? null;

        return new self(is_object($user) ? $user : null);
    }

    public function isMember(): bool
    {
        return $this->user !== null;
    }

    public function uid(): int
    {
        return $this->user === null ? 0 : (int) $this->user->getVar('uid');
    }

    /** @return array<int, int> */
    public function groups(): array
    {
        return $this->user === null ? [] : $this->user->getGroups();
    }

    public function isInGroup(int $group): bool
    {
        return in_array($group, $this->groups());
    }

    public function isAdmin(): bool
    {
        return $this->user !== null && $this->user->isAdmin();
    }
}
