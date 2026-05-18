<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

class GoodInstanceState
{
    public const SAFE_LIMIT = 100;

    protected array $cache = [];

    public function remember(string $key, string $value): string
    {
        return $this->cache[$key] ??= $value;
    }
}
