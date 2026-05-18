# Changelog

All notable changes to `octane-doctor` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/octane-doctor/octane-doctor/compare/0.7.0...HEAD)

## [0.7.0](https://github.com/octane-doctor/octane-doctor/compare/0.6.0...0.7.0) - 2026-05-18

### What's Changed

* Rename Composer package and update repo URLs to octane-doctor org by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/29

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.6.0...0.7.0

## [0.6.0](https://github.com/octane-doctor/octane-doctor/compare/0.5.0...0.6.0) - 2026-05-18

### What's Changed

* Add logo mark and wordmark to art/ by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/27
* Use wordmark image in README hero by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/28

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.5.0...0.6.0

## [0.5.0](https://github.com/octane-doctor/octane-doctor/compare/0.4.0...0.5.0) - 2026-05-18

### What's Changed

* Add social preview image source and rendered PNG to art/ by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/26

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.4.0...0.5.0

## [0.4.0](https://github.com/octane-doctor/octane-doctor/compare/0.3.0...0.4.0) - 2026-05-17

### What's Changed

* Polish GitHub homepage: hero, example output, community files by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/24
* Add markdown feature-request issue template by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/25

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.3.0...0.4.0

## [0.3.0](https://github.com/octane-doctor/octane-doctor/compare/0.2.1...0.3.0) - 2026-05-16

### What's Changed

* Safe-list Filament static configuration properties by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/23

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.2.1...0.3.0

## [0.2.1](https://github.com/octane-doctor/octane-doctor/compare/0.2.0...0.2.1) - 2026-05-15

### What's Changed

* Exclude internal docs and tooling from composer dist by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/21

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.2.0...0.2.1

## [0.2.0](https://github.com/octane-doctor/octane-doctor/compare/0.1.1...0.2.0) - 2026-05-15

### What's Changed

* Render scan output through Termwind for Pest-style terminal UI by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/20

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.1.1...0.2.0

## [0.1.1](https://github.com/octane-doctor/octane-doctor/compare/0.1.0...0.1.1) - 2026-05-15

### What's Changed

* Cleanup 0.1.0 release: drop duplicate header and v prefix by @gayansanjeewa in https://github.com/octane-doctor/octane-doctor/pull/19

**Full Changelog**: https://github.com/octane-doctor/octane-doctor/compare/0.1.0...0.1.1

## [0.1.0](https://github.com/octane-doctor/octane-doctor/releases/tag/0.1.0) - 2026-05-15

First MVP release. Implements the scanner foundation, CLI surface, and seven high signal rules described in `octane_doctor_specification.md`.

### Added

#### Scanner foundation

* `Finding` value object with deterministic 16 character fingerprint, severity, category, file location, and remediation fields (#2).
* `Severity` and `Category` enums covering the categories defined in the spec (#2).
* `Rule` contract and `ScanContext` for rules to receive paths and the host application (#2).
* `Scanner` runner that aggregates findings and sorts them by severity, rule id, file, line (#2).
* `RuleRegistry` that resolves built in and custom rule classes through the Laravel container, rejecting anything that does not implement the contract (#2).
* `RuleExplanation` value object with `whyItMatters`, `remediation`, `examples`, and optional `docsUrl` (#14).

#### Built in rules

* `mutable-static-state` (#3): flags mutable static class and trait properties. Safe list for documented Laravel idioms such as `JsonResource::$wrap` and Eloquent `Model::$snakeAttributes` (#5).
* `risky-helpers-in-constructor` (#6): flags `request()`, `auth()`, `session()`, and the matching facades when called from a class constructor. Per dispatch safe list skips events, jobs, mailables, notifications, and form requests.
* `request-context-as-property` (#7): flags typed properties (regular and constructor promoted) holding the current Request, auth Guard, Route, Router, or Session store. Adds `Controller` to the safe list because Laravel resolves controllers per request.
* `container-as-property` (#8): flags classes that hold the container or `Application` as an instance property. Safe list covers `ServiceProvider`.
* `request-in-singleton` (#9): walks `Container::getBindings()` and flags singleton bound services whose constructor accepts a request scoped framework object. Found a real third party leak (`Spatie\Activitylog\CauserResolver`) on the sandbox during validation.
* `suspicious-singleton-name` (#10): heuristic name matcher for singleton bindings that look like per request state (CurrentUser, TenantContext, RequestState, etc.).
* `octane-config-check` (#11, refined in #17): two layered configuration check covering "Octane not installed" (info) and "config not published" (low).

#### CLI commands

* `octane-doctor:scan` with `--fail-on`, `--format=table|json`, and `--no-baseline` (#2, #4, #12).
* `octane-doctor:baseline` with `--path` override (#12).
* `octane-doctor:explain <rule-id>` for the long form rule description, plus a no argument listing of every registered rule (#14).

#### Suppression

* Baseline workflow: snapshot fingerprints to a JSON file and filter known findings on subsequent scans (#12).
* `octane-doctor.ignore` config key: permanent suppression by rule id or fingerprint, reported separately from the baseline in both table and JSON output (#16).

#### Tooling

* Default config registers all built in rules and exposes `fail_on`, `output`, `paths`, `rules`, `custom_rules`, `ignore`, and `baseline` keys.
* Pint configuration excludes `sandbox/`, `vendor/`, and `build/` so lint runs do not walk into host applications checked out for ad hoc testing (#5).
* GitHub Actions matrix covers PHP 8.3 / 8.4 / 8.5 against Laravel 11 / 12 / 13 with prefer lowest and prefer stable resolutions.

#### Documentation

* README with installation, quick start, JSON output, severity threshold, baseline workflow, explain command, configuration, built in rule table, custom rule extension, CI usage, and auto fix policy (#13, #15).
* Custom rule example aligned with the current `Rule` contract including `explanation()` (#15).

### Fixed

* `OctaneConfigCheck` medium severity branch removed: the assumption that user `octane.flush` should contain baseline framework services was wrong. Laravel Octane resets framework state internally regardless of the user flush array (#17).
* `RequestContextAsProperty` no longer matches `Illuminate\Http\Concerns\InteractsWithInput`: traits cannot be property types, so the entry was dead code (#17).

### Deliberately out of scope for this release

* Rector based auto fix (spec section 11).
* Pest plugin (`it()->octaneReady()`, spec section 14).
* Readiness scoring (spec section 19).
* The deferred rule list in spec section 10 (GraphQL context, multitenancy packages, queue interaction, etc.).
