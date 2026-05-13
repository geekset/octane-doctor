<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

class CapturesAuthAlias
{
    protected $user;

    public function __construct()
    {
        $this->user = \Auth::user();
    }
}
