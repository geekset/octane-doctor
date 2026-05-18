<?php

namespace OctaneDoctor\Tests\Fixtures\StaticState;

trait BadTrait
{
    public static ?string $tenant = null;
}
