<?php

namespace Geekset\OctaneDoctor\Scanning;

use Geekset\OctaneDoctor\Exceptions\InvalidRule;
use Geekset\OctaneDoctor\Rules\Rule;
use Illuminate\Contracts\Foundation\Application;

class RuleRegistry
{
    /**
     * @param  array<int, class-string<Rule>>  $builtIn
     * @param  array<int, class-string<Rule>>  $custom
     */
    public function __construct(
        protected Application $app,
        protected array $builtIn,
        protected array $custom,
    ) {}

    /**
     * @return array<int, Rule>
     */
    public function all(): array
    {
        $rules = [];

        foreach ([...$this->builtIn, ...$this->custom] as $class) {
            $rules[] = $this->resolve($class);
        }

        return $rules;
    }

    protected function resolve(string $class): Rule
    {
        if (! is_subclass_of($class, Rule::class)) {
            throw InvalidRule::doesNotImplementContract($class);
        }

        return $this->app->make($class);
    }
}
