<div align="center">

<img src="art/logo-wordmark.png" alt="Octane Doctor" width="540">

**A Laravel Octane readiness scanner.**

_Detect long-lived worker risks in Laravel apps before they bite production._

[![Latest Version on Packagist](https://img.shields.io/packagist/v/geekset/octane-doctor.svg?style=flat-square)](https://packagist.org/packages/geekset/octane-doctor)
[![PHP Version](https://img.shields.io/packagist/dependency-v/geekset/octane-doctor/php?style=flat-square)](https://packagist.org/packages/geekset/octane-doctor)
[![Tests](https://img.shields.io/github/actions/workflow/status/geekset/octane-doctor/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/geekset/octane-doctor/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Code Style](https://img.shields.io/github/actions/workflow/status/geekset/octane-doctor/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/geekset/octane-doctor/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![License](https://img.shields.io/packagist/l/geekset/octane-doctor.svg?style=flat-square)](LICENSE.md)
[![Total Downloads](https://img.shields.io/packagist/dt/geekset/octane-doctor.svg?style=flat-square)](https://packagist.org/packages/geekset/octane-doctor)

</div>

Octane Doctor looks at an existing Laravel application, reports patterns that tend to break under long lived workers, and explains each finding in terms a developer can act on.

It is built for teams who already have a real codebase. The default rules are conservative on purpose. The package favours a small set of high signal checks over a wide net of guesses.

## What it can do

* Detect common Octane risk patterns in Laravel code (mutable static state, request scoped objects stored on long lived services, request and auth helpers used in singleton constructors, container instances cached on services, missing Octane configuration).
* Explain why each finding matters under Octane and how to fix it.
* Produce JSON output that fits into CI pipelines.
* Snapshot today's findings into a baseline so a team can adopt the scanner gradually and fail CI only on new findings.

## What it does not do

* Guarantee that an application is Octane safe. Readiness is a risk signal, not a certificate.
* Replace load testing, profiling, or production validation.
* Automatically fix issues. The MVP focuses on accurate findings and useful remediation text. Automated rewrites are deliberately out of scope.
* Understand arbitrary domain logic. Custom rules cover patterns the built in rule set cannot know about.

## Example output

Two contrived classes:

```php
// app/Services/UserCache.php
class UserCache
{
    protected static array $cache = [];
}

// app/Services/ReportService.php
class ReportService
{
    public function __construct(protected Request $request) {}
}
```

Run the scanner:

```bash
php artisan octane-doctor:scan
```

What the developer sees in the terminal:

```text
   HIGH  request-context-as-property Request-scoped object stored as a class property
    at app/Services/ReportService.php:10
    Class App\Services\ReportService stores Illuminate\Http\Request on property $request.
    Why: Under Octane the same object instance is reused across requests. A property
         holding the current Request, auth guard, route, or session freezes to the
         request that constructed the class and stays stale for every later request.
    Fix: Receive the request-scoped object as a method parameter, resolve it on
         demand through the container, or move the binding to scoped() so a fresh
         instance is built per request.

   MEDIUM  mutable-static-state Mutable static state
    at app/Services/UserCache.php:7
    Class App\Services\UserCache declares mutable static property $cache.
    Why: Static class properties persist across requests under Octane workers. Any
         mutation written during one request stays visible to every subsequent
         request handled by the same worker.
    Fix: Move the state onto an instance, behind a scoped() container binding, or
         into a per-request cache. If the value is constant, use a class constant.

  Total: 2 (high: 1, medium: 1, low: 0, info: 0) in 10.0 ms
```

Severity badges (`HIGH` red, `MEDIUM` yellow, `LOW` blue, `INFO` grey) are coloured in the actual terminal output.

## Installation

```bash
composer require geekset/octane-doctor --dev
```

The package self registers via Laravel's package discovery. Publish the config when you want to customise it:

```bash
php artisan vendor:publish --tag=octane-doctor-config
```

## Supported versions

* PHP 8.3 and 8.4
* Laravel 11.x, 12.x, and 13.x

The matrix matches the support window of Laravel Octane itself.

## Quick start

Run a scan against the configured paths (`app/` and `config/` by default):

```bash
php artisan octane-doctor:scan
```

You will see one block per finding, including the severity, rule id, file location, summary, why it matters, and a remediation hint. The command exits with a non zero status when any finding meets the configured severity threshold.

### JSON output

```bash
php artisan octane-doctor:scan --format=json
```

The JSON document is schema versioned and stable across point releases:

```json
{
    "schema_version": "1",
    "summary": {
        "total": 2,
        "by_severity": { "high": 1, "medium": 1, "low": 0, "info": 0 },
        "by_category": { "static-state": 1, "singleton-safety": 1 },
        "scanned_paths": ["/var/www/app"],
        "duration_ms": 312.5,
        "baselined": 0
    },
    "findings": [
        {
            "rule_id": "request-in-singleton",
            "severity": "high",
            "fingerprint": "..."
        }
    ]
}
```

### Severity threshold

```bash
# Fail only on high severity findings (default for fresh installs).
php artisan octane-doctor:scan --fail-on=high

# Fail on anything medium and above.
php artisan octane-doctor:scan --fail-on=medium

# Run as an informational report; always exits 0.
php artisan octane-doctor:scan --fail-on=never
```

`--fail-on` overrides the `fail_on` value in `config/octane-doctor.php`.

## Baseline workflow

Most legacy applications have findings on day one. The baseline command records the current set of findings as already acknowledged so future scans only react to new ones.

```bash
# Snapshot today's findings.
php artisan octane-doctor:baseline

# Subsequent scans suppress baselined findings and exit 0 unless something new appears.
php artisan octane-doctor:scan
```

The baseline path defaults to `storage/app/octane-doctor-baseline.json` and can be overridden via `octane-doctor.baseline` in config or `--path` on the command. Commit the baseline so CI sees the same view as developers locally.

Pass `--no-baseline` to ignore the baseline for an audit run:

```bash
php artisan octane-doctor:scan --no-baseline
```

## Explaining a rule

Each finding is short on purpose. When you want the full picture for a rule, ask for it by id:

```bash
php artisan octane-doctor:explain request-in-singleton
```

Output covers the title, severity, category, why it matters, remediation, and concrete examples that show a flagged form versus a safe form of the pattern.

Run the command without an argument to see every registered rule (built in plus custom):

```bash
php artisan octane-doctor:explain
```

## Configuration

`config/octane-doctor.php`:

```php
return [
    'fail_on' => 'high',
    'output' => 'table',
    'paths' => [
        app_path(),
        config_path(),
    ],
    'rules' => [
        // Built in rules registered by default.
    ],
    'custom_rules' => [
        // Add your own rule classes here.
    ],
    'ignore' => [
        // Finding fingerprints or rule ids to suppress.
    ],
    'baseline' => storage_path('app/octane-doctor-baseline.json'),
];
```

To disable a built in rule, copy the default `rules` list and remove the entry you do not want.

### Suppressing findings

Use `octane-doctor.ignore` for permanent suppressions that should never be reported again. Each entry is matched against both the rule id and the finding fingerprint:

```php
'ignore' => [
    // Turn off an entire rule for this project.
    'suspicious-singleton-name',

    // Suppress one specific finding by its fingerprint (copy it from the JSON output).
    'a1b2c3d4e5f60789',
],
```

The baseline workflow above is the right choice when you want to acknowledge today's findings and surface new ones. The ignore list is the right choice when you have already decided a pattern is acceptable for your codebase.

## Built in rules

| Rule id | Severity | Category | What it flags |
| --- | --- | --- | --- |
| `mutable-static-state` | medium | static-state | Mutable static class or trait properties. |
| `risky-helpers-in-constructor` | medium | request-state | Calls to `request()`, `auth()`, `session()`, or the matching facades inside a class constructor. |
| `request-context-as-property` | high | request-state | Properties typed as Request, the auth guard, Route, or Session on long lived services. |
| `container-as-property` | medium | container-lifecycle | The container or Application stored as an instance property. |
| `request-in-singleton` | high | singleton-safety | Singleton bound services whose constructor accepts a request scoped framework object. |
| `octane-config-check` | info / low | configuration | Octane not installed, or installed but `config/octane.php` not published. |

Each rule contains a per dispatch and per request safe list so events, mailables, notifications, form requests, and controllers are not flagged for patterns that are documented Laravel idioms in those contexts.

## Custom rules

Implement `Geekset\OctaneDoctor\Rules\Rule` and register the class in `octane-doctor.custom_rules`:

```php
namespace App\Octane\Rules;

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Rules\Rule;
use Geekset\OctaneDoctor\Rules\RuleExplanation;
use Geekset\OctaneDoctor\Scanning\ScanContext;

class ForbidTenantContext implements Rule
{
    public function id(): string
    {
        return 'app-no-tenant-context-singleton';
    }

    public function title(): string
    {
        return 'TenantContext must not be a singleton';
    }

    public function severity(): Severity
    {
        return Severity::High;
    }

    public function category(): Category
    {
        return Category::SingletonSafety;
    }

    public function explanation(): RuleExplanation
    {
        return new RuleExplanation(
            whyItMatters: 'TenantContext holds the current tenant for one request only.',
            remediation: 'Bind it with scoped() so each request gets its own instance.',
            examples: [
                '$this->app->singleton(TenantContext::class); // flagged',
                '$this->app->scoped(TenantContext::class);    // safe',
            ],
        );
    }

    public function run(ScanContext $context): iterable
    {
        // Walk $context->paths or inspect $context->app->getBindings()
        // and yield Finding instances.
    }
}
```

```php
// config/octane-doctor.php
'custom_rules' => [
    App\Octane\Rules\ForbidTenantContext::class,
],
```

Findings emitted by custom rules use the same shape, fingerprint, and CLI output as the built in rules.

## CI usage

The minimum viable CI step is one command:

```yaml
# GitHub Actions example.
- name: Octane readiness
  run: php artisan octane-doctor:scan --fail-on=high
```

For staged rollouts, capture the baseline, commit it to the repo, then have CI run the same `octane-doctor:scan` command. Anything new becomes a failing build. Anything already baselined stays quiet.

## Auto fix policy

There is no automated fix in this release. Every finding contains a remediation hint instead. This is intentional: getting the findings right is a prerequisite for trusting an automatic rewrite, and an early auto fix would damage that trust on the first false positive.

## Development

```bash
composer test       # Pest test suite
composer analyse    # PHPStan level 5
composer lint       # Pint
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the full development workflow.

## Security

Report security issues privately. See [SECURITY.md](SECURITY.md) for the supported versions matrix and reporting instructions.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). All contributors must follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
