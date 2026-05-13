<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\StaticState;

class BadCounter
{
    private static int $count = 0;

    public static function increment(): void
    {
        self::$count++;
    }
}
