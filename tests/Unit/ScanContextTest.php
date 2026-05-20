<?php

use OctaneDoctor\Scanning\ScanContext;

it('treats every path as in scope when no paths are configured', function () {
    $context = new ScanContext(app(), []);

    expect($context->isPathInScope('/anywhere/app/Foo.php'))->toBeTrue();
});

it('returns true when the absolute path sits under a configured path', function () {
    $context = new ScanContext(app(), ['/project/app']);

    expect($context->isPathInScope('/project/app/Services/Foo.php'))->toBeTrue();
});

it('returns false when the absolute path is outside every configured path', function () {
    $context = new ScanContext(app(), ['/project/app']);

    expect($context->isPathInScope('/project/vendor/spatie/laravel-activitylog/src/CauserResolver.php'))->toBeFalse();
});

it('returns false when the absolute path is null', function () {
    $context = new ScanContext(app(), ['/project/app']);

    expect($context->isPathInScope(null))->toBeFalse();
});

it('tolerates trailing slashes on configured paths', function () {
    $context = new ScanContext(app(), ['/project/app/']);

    expect($context->isPathInScope('/project/app/Foo.php'))->toBeTrue();
});

it('does not match overlapping prefixes that are not directory boundaries', function () {
    $context = new ScanContext(app(), ['/project/app']);

    expect($context->isPathInScope('/project/application/Foo.php'))->toBeFalse();
});
