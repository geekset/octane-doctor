<?php

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Builtin\OctaneConfigCheck;
use Geekset\OctaneDoctor\Scanning\ScanContext;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/octane-doctor-config-test-'.uniqid();
    File::makeDirectory($this->tempDir.'/config', 0o755, true);

    app()->setBasePath($this->tempDir);
});

afterEach(function () {
    if (is_dir($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

function runOctaneConfigCheckRule(): array
{
    $rule = new OctaneConfigCheck;

    return iterator_to_array(
        $rule->run(new ScanContext(app(), [])),
        false,
    );
}

it('emits an Info finding when laravel/octane is not in composer.json', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['laravel/framework' => '^12.0'],
    ]));

    $findings = runOctaneConfigCheckRule();

    expect($findings)->toHaveCount(1)
        ->and($findings[0])->toBeInstanceOf(Finding::class)
        ->and($findings[0]->severity)->toBe(Severity::Info)
        ->and($findings[0]->category)->toBe(Category::Configuration)
        ->and($findings[0]->title)->toBe('Laravel Octane is not installed');
});

it('emits a Low finding when Octane is installed but config is not published', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['laravel/octane' => '^2.0'],
    ]));

    $findings = runOctaneConfigCheckRule();

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->severity)->toBe(Severity::Low)
        ->and($findings[0]->title)->toBe('Octane config has not been published');
});

it('returns no findings when Octane is installed and config is published, regardless of flush contents', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['laravel/octane' => '^2.0'],
    ]));
    file_put_contents($this->tempDir.'/config/octane.php', '<?php return [];');

    config()->set('octane.flush', []);

    $findings = runOctaneConfigCheckRule();

    expect($findings)->toBe([]);
});

it('detects laravel/octane when it lives in require-dev', function () {
    file_put_contents($this->tempDir.'/composer.json', json_encode([
        'require' => ['laravel/framework' => '^12.0'],
        'require-dev' => ['laravel/octane' => '^2.0'],
    ]));

    $findings = runOctaneConfigCheckRule();

    expect($findings)->toHaveCount(1)
        ->and($findings[0]->title)->toBe('Octane config has not been published');
});
