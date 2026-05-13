<?php

namespace Geekset\OctaneDoctor\Scanning;

use Geekset\OctaneDoctor\Exceptions\InvalidRule;
use Geekset\OctaneDoctor\Rules\Rule;
use Illuminate\Contracts\Foundation\Application;

/*
 * Resolves rule classes (built-in and host-provided) into instances
 * for the Scanner to execute. Built-in and custom lists are kept
 * separate so users can disable shipped rules in config without
 * losing their own customisations, and so spec section 15's custom
 * rule extension point stays a first-class concept.
 *
 * Resolution goes through the Laravel container so rules can depend
 * on framework services (router, container, config) via constructor
 * injection without the registry caring what those services are.
 */
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

        /*
         * Built-in rules run first so shipped findings stay grouped
         * together in deterministic order before any host overrides
         * or extensions append to the list.
         */
        foreach ([...$this->builtIn, ...$this->custom] as $class) {
            $rules[] = $this->resolve($class);
        }

        return $rules;
    }

    /*
     * Validate the contract before instantiating so a misconfigured
     * custom_rules entry fails loudly with a clear message instead of
     * surfacing as a confusing type error during scan execution.
     */
    protected function resolve(string $class): Rule
    {
        if (! is_subclass_of($class, Rule::class)) {
            throw InvalidRule::doesNotImplementContract($class);
        }

        return $this->app->make($class);
    }
}
