<?php

namespace Geekset\OctaneDoctor\Exceptions;

use Exception;
use Geekset\OctaneDoctor\Rules\Rule;

class InvalidRule extends Exception
{
    public static function doesNotImplementContract(string $class): self
    {
        return new self("Rule [{$class}] must implement ".Rule::class.'.');
    }
}
