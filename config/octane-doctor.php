<?php

use Geekset\OctaneDoctor\Rules\Builtin\ContainerAsProperty;
use Geekset\OctaneDoctor\Rules\Builtin\MutableStaticState;
use Geekset\OctaneDoctor\Rules\Builtin\RequestContextAsProperty;
use Geekset\OctaneDoctor\Rules\Builtin\RequestInSingleton;
use Geekset\OctaneDoctor\Rules\Builtin\RiskyHelpersInConstructor;

return [

    /*
     * Lowest severity that should make the scan command exit with a
     * non-zero status. Use null or "never" to always exit 0. Override
     * per run with --fail-on. Supported: high, medium, low, info.
     */
    'fail_on' => 'high',

    /*
     * Default output format for the scan command. Override per run
     * with --format. Supported: table, json.
     */
    'output' => 'table',

    /*
     * Application paths to scan. Defaults to app/ and config/. Add or
     * remove paths to scope the scan to specific parts of the codebase.
     */
    'paths' => [
        app_path(),
        config_path(),
    ],

    /*
     * Built-in rule classes to run. Listed in this config (rather than
     * hard-coded) so projects can disable individual rules without
     * changing the service provider.
     */
    'rules' => [
        MutableStaticState::class,
        RiskyHelpersInConstructor::class,
        RequestContextAsProperty::class,
        ContainerAsProperty::class,
        RequestInSingleton::class,
    ],

    /*
     * Custom rule classes registered by the host application. Each must
     * implement Geekset\OctaneDoctor\Rules\Rule.
     */
    'custom_rules' => [
    ],

    /*
     * Finding fingerprints or rule IDs to suppress. Suppression keeps
     * the finding out of the report without disabling the rule itself.
     */
    'ignore' => [
    ],

    /*
     * Baseline file path. Used by the (forthcoming) baseline command
     * to record currently accepted findings so future runs only fail
     * on new ones.
     */
    'baseline' => storage_path('app/octane-doctor-baseline.json'),
];
