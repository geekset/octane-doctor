<?php

namespace Geekset\OctaneDoctor\Tests\Fixtures\ConstructorHelpers;

use Illuminate\Foundation\Http\FormRequest;

class SafeFormRequestCapturesAuth extends FormRequest
{
    public $userId;

    public function __construct()
    {
        parent::__construct();

        $this->userId = auth()->id();
    }
}
