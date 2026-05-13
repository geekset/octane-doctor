<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

class CapturesRequest
{
    protected $payload;

    public function __construct()
    {
        $this->payload = request()->all();
    }
}
