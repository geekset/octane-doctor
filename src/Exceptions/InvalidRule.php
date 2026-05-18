<?php

namespace OctaneDoctor\Exceptions;

use Exception;
use OctaneDoctor\Rules\Rule;

class InvalidRule extends Exception
{
    public static function doesNotImplementContract(string $class): self
    {
        return new self("Rule [{$class}] must implement ".Rule::class.'.');
    }
}
