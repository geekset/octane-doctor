<?php

use Illuminate\Support\Facades\Artisan;

it('produces the README example findings against the demo fixtures', function () {
    config()->set('octane-doctor.paths', [__DIR__.'/../Fixtures/Demo']);

    Artisan::call('octane-doctor:scan', ['--format' => 'json', '--fail-on' => 'never']);

    $payload = json_decode(Artisan::output(), true);

    $rules = collect($payload['findings'])->pluck('rule_id')->all();

    expect($rules)
        ->toContain('request-context-as-property')
        ->toContain('mutable-static-state');
});
