<?php

namespace App\Services;

class UserCache
{
    protected static array $cache = [];

    public function remember(int $userId, callable $resolver): mixed
    {
        return self::$cache[$userId] ??= $resolver();
    }
}
