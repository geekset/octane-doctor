<?php

namespace OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

use Illuminate\Support\Facades\Auth;

class CapturesAuthFacade
{
    protected $userId;

    public function __construct()
    {
        $this->userId = Auth::id();
    }
}
