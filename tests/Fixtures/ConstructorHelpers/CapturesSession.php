<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

class CapturesSession
{
    protected $cart;

    public function __construct()
    {
        $this->cart = session('cart');
    }
}
