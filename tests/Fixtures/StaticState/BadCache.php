<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

class BadCache
{
    protected static array $cache = [];

    public static function remember(string $key, string $value): string
    {
        return self::$cache[$key] ??= $value;
    }
}
