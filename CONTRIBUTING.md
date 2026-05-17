# Contributing to Octane Doctor

Thank you for taking the time to contribute. This document covers the development workflow, code style, and rule authoring guidelines.

## Prerequisites

* PHP 8.3 or 8.4
* Composer 2
* Git

The matrix targets the same PHP and Laravel versions as Laravel Octane itself.

## Local setup

```bash
git clone git@github.com:geekset/octane-doctor.git
cd octane-doctor
composer install
```

Verify the install:

```bash
composer test       # Pest test suite
composer analyse    # PHPStan level 5
composer lint       # Pint
```

All three commands must pass before opening a pull request.

## Branch and PR convention

* Branch off `main` for every change. Naming pattern: `feat/<short-slug>`, `fix/<short-slug>`, `chore/<short-slug>`, `docs/<short-slug>`.
* One pull request per concern. Bundle a code change with its tests and its documentation in the same PR. Do not bundle unrelated cleanup.
* Squash merge is the default. Keep individual commits clean enough to be useful in history if the PR is rebased instead.

## Commit style

Use [Conventional Commits](https://www.conventionalcommits.org/). Common prefixes:

* `feat:` new functionality
* `fix:` bug fix
* `refactor:` internal change with no functional difference
* `docs:` documentation only
* `test:` test only
* `chore:` build, tooling, or repo metadata
* `perf:` performance improvement

The PR title becomes the squash commit subject, so the same convention applies there.

## Code style

* `composer lint` runs Pint with the project ruleset. The CI workflow auto-fixes style on push, but lint locally first to keep diffs clean.
* PHPStan runs at level 5 with no baseline. Fix issues at the source, do not add ignore lines without justification.
* Strict types: `declare(strict_types=1);` is not currently required at the top of files, but new files should match the style of nearby files.
* Prefer constructor property promotion and typed properties.

## Testing

* Pest 3 is the test framework. Tests live in `tests/Unit` and `tests/Feature`.
* Every new rule needs at least one positive fixture, one negative fixture, and a test asserting the metadata shape (rule id, severity, category, file path, line).
* Run a single file: `vendor/bin/pest tests/Unit/Rules/MutableStaticStateTest.php`.
* Run the full suite in compact mode: `composer test`.

## Writing a new rule

A rule implements `Geekset\OctaneDoctor\Rules\Rule`. The interface requires:

* `id()` returns the kebab-case identifier used on the CLI.
* `title()` returns a short human label.
* `severity()` returns one of `Severity::High`, `Severity::Medium`, `Severity::Low`, `Severity::Info`.
* `category()` returns a `Category` enum case.
* `explanation()` returns a `RuleExplanation` value object with `whyItMatters`, `remediation`, optional `examples`, and optional `docsUrl`.
* `run(ScanContext $context): iterable` yields `Finding` instances.

Register the new rule in `config/octane-doctor.php` under `rules`. Use `src/Rules/Builtin/MutableStaticState.php` as a reference implementation, especially for the AST visitor pattern with `NameResolver`.

If the rule uses a safe-list to suppress false positives on framework idioms, document why each entry is on the list (in the class docblock or constant docblock).

## Running against a host application

Use a Composer path repository to point an existing Laravel app at your local checkout:

```bash
cd /path/to/host-app
composer config repositories.octane-doctor path /path/to/octane-doctor
composer require geekset/octane-doctor:@dev --dev
php artisan octane-doctor:scan
```

To revert: `git checkout composer.json composer.lock` and `rm -rf vendor/geekset`.

## Reporting issues

Use the issue templates under [.github/ISSUE_TEMPLATE](.github/ISSUE_TEMPLATE). For security issues, follow [SECURITY.md](SECURITY.md) instead.

## Code of Conduct

Participation is governed by the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md).
