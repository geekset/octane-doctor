<?php

namespace OctaneDoctor\Tests\Fixtures\SuspiciousSingleton;

class BenignCacheService
{
    public function get(string $key): ?string
    {
        return null;
    }
}
