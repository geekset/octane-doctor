# Octane Doctor — Spec-Driven Development Pack

## 1. Product Intent

**Working name:** Octane Doctor\
**Category:** Open-source PHP package for Laravel\
**Primary audience:** Teams with legacy Laravel applications evaluating or adopting Laravel Octane

### Problem statement

Teams want to move existing Laravel applications to Octane, but they do not know:

- whether an app is safe to run under long-lived workers
- how difficult the migration will be
- what must remain true after migration to avoid regressions

Octane Doctor exists to:

- assess Octane readiness before migration
- explain concrete risks in a developer-friendly way
- auto-fix a safe subset of issues where mechanical refactors are possible
- keep applications Octane-compatible over time through CI guardrails

### Product promise

Octane Doctor helps Laravel teams assess, migrate, and continuously protect applications running under long-lived Octane workers.

---

## 2. Product Goals

### Goals

- Detect common Octane risk patterns in Laravel applications.
- Provide actionable explanations for each finding.
- Offer safe automated refactors for a limited subset of findings.
- Provide test and CI guardrails that prevent regressions after migration.
- Support the Laravel and PHP versions in the current Octane support window.
- Be legacy-friendly in adoption, setup, and output.

### Non-goals

- Guarantee that an application is fully Octane-safe.
- Automatically fix every risky pattern.
- Replace load testing, profiling, or production validation.
- Deeply understand arbitrary domain logic without project-specific extensions.
- Support Laravel versions outside the current Octane support window.

---

## 3. Design Principles

1. **Legacy first**\
   The package must provide value to large, imperfect Laravel apps, not only greenfield codebases.

2. **Guardrails over false certainty**\
   Findings should be framed as risk signals, not absolute truth.

3. **Explain before fixing**\
   Every finding must clearly state why it is risky under Octane and how to fix it.

4. **Safe automation only**\
   Auto-fixes must be conservative. If intent is unclear, the package must report and defer to the developer.

5. **Framework-native DX**\
   The developer experience should feel like Laravel and Pest, not like a generic enterprise scanner.

6. **Composable architecture**\
   Scanning, test integration, and automated fixes must be separable packages or modules.

---

## 4. Package Topology

Keep the initial architecture simple.

### 4.1 Single package for MVP

**Working package name candidate:** `octane-doctor`

Responsibilities:

- rule engine
- finding model
- scan orchestration
- report generation
- Laravel integration
- CLI command(s)
- config file
- baseline support

### 4.2 MVP packaging rule

For the first release, do **not** split the project into multiple installable packages.

Reasoning:

- faster iteration
- lower maintenance overhead
- easier contributor onboarding
- avoids premature abstraction

### 4.3 Dependency rule

The MVP must **not** depend on third-party Octane testing, health-check, or observability packages for core functionality.

The scanner, findings, rules, CLI, and baseline support must be fully owned by this package.

### 4.4 Future extraction path

If the project proves successful, optional integrations may later be extracted into:

- Pest integration package
- Rector integration package
- runtime confidence package

But these are explicitly post-MVP concerns.

---

## 5. Supported Versions

### Scope

The package must target the same Laravel and PHP support window as current Laravel Octane.

### Versioning policy

- Support the currently supported Octane-compatible Laravel major versions.
- Support the PHP versions required by those Laravel versions.
- Drop unsupported framework versions in a major release of Octane Doctor.

### Engineering rule

Avoid version-specific branching in the domain layer. Keep framework compatibility isolated behind capability adapters.

### Capability adapter examples

- container binding inspection
- route/controller introspection
- AST-based analysis wrappers
- testbench bootstrapping
- Pest version adapter layer if needed

---

## 6. User Personas

### Persona A — Legacy app team evaluating Octane

Needs a migration readiness report and a rough idea of risk.

### Persona B — Team already on Octane

Needs CI guardrails to stop regressions.

### Persona C — Platform or architecture team

Needs consistent rules across multiple Laravel applications.

### Persona D — Open-source contributor

Needs a clean rule API and a predictable testing surface.

---

## 7. Primary Use Cases

### Use case 1 — Pre-migration readiness scan

A team installs the package and runs:

```bash
php artisan octane-doctor:scan
```

Expected result:

- readiness score or risk summary
- categorized findings
- explanation per finding
- suggested remediation per finding
- machine-readable output option for CI or dashboards

### Use case 2 — Safe auto-fix pass

A team runs:

```bash
php artisan octane-doctor:fix --safe
```

Expected result:

- only safe, deterministic changes are applied
- all other issues remain reported but untouched
- diff and summary are shown

### Use case 3 — Continuous compatibility check

A team adds Pest tests or CI checks such as:

```php
it('remains octane compatible')->octaneCompatible();
```

Expected result:

- obvious high-risk patterns fail CI
- accepted risks can be configured or suppressed

### Use case 4 — Multitenant or per-request isolation guard

A team verifies that tenant or user-specific context does not leak across requests.

This likely belongs in phase 2 or later.

---

## 8. Core Concepts

### 8.1 Finding

A finding represents one detected risk.

Required fields:

- id
- title
- severity
- category
- file path
- line(s)
- symbol or class context
- summary
- why\_it\_matters
- remediation
- autofixable (boolean)
- autofix\_strategy\_id (nullable)
- docs\_url (nullable)
- fingerprint for suppression tracking

### 8.2 Severity

Initial levels:

- `high`
- `medium`
- `low`
- `info`

### 8.3 Categories

Initial categories:

- `container-lifecycle`
- `request-state`
- `static-state`
- `singleton-safety`
- `runtime-isolation`
- `package-compatibility`
- `configuration`
- `unknown-risk`

### 8.4 Rule

A rule inspects code, config, or runtime behaviour and yields zero or more findings.

Rule contract requirements:

- deterministic
- testable in isolation
- framework-version aware where necessary
- able to provide a clear remediation message

---

## 9. MVP Rule Set

The first release must focus on a small, high-value rule set.

### Rule 1 — Request injected into singleton-bound service

Detect service classes that are singleton-bound and directly depend on request-scoped objects such as request, authenticated user context, route context, or tenant context.

Expected output:

- identify the binding
- identify the risky constructor or property dependency
- explain long-lived worker implications
- suggest passing request data at method call time or using a scoped resolver

### Rule 2 — Container or application instance stored in long-lived service

Detect storing `Application`, container, or resolver instances on services that may live across requests.

Expected output:

- explain stale container or stale resolution risk
- recommend resolving dependencies at execution time only where appropriate, or redesigning service boundaries

### Rule 3 — Mutable static state

Detect application classes with mutable static properties likely to hold request-specific or runtime state.

Expected output:

- explain why static state is dangerous with long-lived workers
- recommend instance-based or scoped state instead

### Rule 4 — Singleton bindings for request-specific services

Detect suspicious `singleton()` bindings for classes whose names, dependencies, or usage patterns imply per-request state.

Examples:

- current user context
- tenant context
- locale context
- request context
- filter state

Expected output:

- recommend `scoped()` when appropriate
- mark as high confidence only when evidence is strong

### Rule 5 — Request or auth context stored as object property

Detect storing request-derived data on object state in classes likely to outlive a single request.

Expected output:

- explain cross-request state leak risk
- suggest method-level parameters or scoped context access patterns

### Rule 6 — Risky framework helper usage in constructors or bootstrapping

Detect patterns like calling `request()`, `auth()`, or similar helpers inside constructors or early lifecycle code for long-lived services.

Expected output:

- explain that the captured context may become stale
- recommend moving reads to invocation time

### Rule 7 — Octane configuration sanity checks

Inspect relevant configuration and flag obviously risky or unsupported defaults where appropriate.

Examples:

- missing or suspicious worker reset configuration
- environment setup that is incompatible with intended usage
- missing recommended production settings where the package can speak with confidence

This rule must be conservative and version-aware.

---

## 10. Deferred Rule Candidates

These are valid but should not block MVP.

- package ecosystem compatibility audit
- memory growth smoke test
- repeated sequential request leak checks
- GraphQL context persistence detection
- event listener state persistence
- cache misuse heuristics
- queue worker and Octane interaction checks
- coroutine-specific checks
- Livewire or Filament-specific heuristics
- multitenancy package-specific rules

---

## 11. Auto-Fix Policy

### 11.1 MVP policy

Do **not** include automated fixing in the first release.

Reasoning:

- safe Octane-related refactors are harder than they first appear
- false confidence would damage trust early
- the first milestone should prove that findings are accurate and useful

### 11.2 What the package must do instead

For every finding, provide:

- a clear explanation
- why it is risky under Octane
- a suggested manual remediation
- enough context for a developer or AI coding agent to implement the fix safely

### 11.3 Future direction

Rector-based fixing may be added later, but only after the scanner has earned trust and the team has identified a genuinely safe subset of transformations.

---

## 12. CLI Specification

### 12.1 `octane-doctor:scan`

Purpose: scan the application and report findings.

#### Required behaviour

- boot inside a Laravel app
- discover configured rules
- run rules deterministically
- group and sort findings
- display concise terminal output
- support JSON output
- return non-zero exit codes for configurable severity thresholds

#### Example

```bash
php artisan octane-doctor:scan
php artisan octane-doctor:scan --format=json
php artisan octane-doctor:scan --fail-on=high
```

### 12.2 `octane-doctor:baseline`

Purpose: create a baseline for gradual adoption.

#### Required behaviour

- snapshot current findings
- allow future runs to fail only on new findings if configured

### 12.3 `octane-doctor:rules:list`

Purpose: list every registered rule so a developer can see what the scanner is checking and find the id to pass to `rules:view` or `scan --rule`.

#### Required behaviour

- print a table of id, severity, category, and title
- support `--format=json` for machine-readable consumption
- include both built-in and custom rules

#### Example

```bash
php artisan octane-doctor:rules:list
```

### 12.4 `octane-doctor:rules:view`

Purpose: show the full description, remediation guidance, and examples for one rule.

#### Required behaviour

- accept a rule id as the only argument
- fail with a non-zero exit code and a `rules:list` hint when the id is unknown
- print title, severity, category, why-it-matters, remediation, examples, and docs URL

#### Example

```bash
php artisan octane-doctor:rules:view request-in-singleton
```

---

## 13. Configuration Specification

Publish a config file, for example:

```php
return [
    'fail_on' => 'high',
    'output' => 'table',
    'baseline' => storage_path('app/octane-doctor-baseline.json'),
    'ignore' => [
        // fingerprints or rule IDs
    ],
    'rules' => [
        // enable/disable rules
    ],
    'paths' => [
        app_path(),
        config_path(),
    ],
    'custom_rules' => [
        // class strings
    ],
];
```

Requirements:

- rule-level enable/disable support
- path scoping
- suppression support
- baseline support
- severity threshold config
- future extensibility without breaking config shape

---

## 14. Pest Integration Specification

Pest integration is valuable, but it is **not required for MVP**.

### MVP approach

The first release should focus on the scanner and CLI.

If Pest hooks are added early, keep them extremely thin and implemented inside the same package rather than as a separate package.

### Future direction

Potential APIs later:

```php
it('is octane ready')->octaneReady();

it('remains octane compatible')->octaneCompatible();
```

### Design rule

The scanner must remain usable without Pest.

---

## 15. Extensibility Specification

The package must support custom rules.

### Requirements

- users can register custom rule classes
- rules can emit native findings
- custom rules appear in CLI and JSON output consistently
- rules can define IDs, severities, remediation, and docs metadata

### Example use cases

- company-specific tenant context patterns
- custom request context wrappers
- internal base service classes
- app-specific singleton binding conventions

This matters because real legacy apps usually have local abstractions the package cannot know by default.

---

## 16. Output Specification

### Human-readable output goals

- concise summary first
- grouped findings next
- remediation hints included
- avoid noisy walls of text

### JSON output goals

The JSON format must be stable enough for CI or dashboard use.

Suggested top-level keys:

- `summary`
- `findings`
- `meta`
- `version`

Summary fields:

- total count
- count by severity
- count by category
- auto-fixable count
- scanned paths
- duration

---

## 17. Testing Strategy

The package itself must be developed with spec-driven discipline.

### 17.1 Test levels

- unit tests for rules
- integration tests for Laravel bootstrapping
- command tests for CLI UX and exit codes
- fixture-based tests for scanner output
- fixture-based tests for Rector transformations
- Pest plugin tests
- version matrix tests across supported Laravel/PHP combinations where practical

### 17.2 Fixture philosophy

Use small, explicit fixture applications or code samples.

Each fixture should represent one clear risk or one safe case.

### 17.3 Golden output tests

For CLI and JSON output, use stable snapshot or golden-file style tests where appropriate.

### 17.4 Regression tests

Every bug fix must add:

- a failing fixture or scenario
- a test that proves the expected finding or absence of false positive

---

## 18. Runtime and CI Expectations

### CI requirements for this package

- test matrix for supported Laravel/PHP combinations
- static analysis
- coding standards
- fixture-based scanner tests

### Consumer CI expectations

For MVP, users should be able to run:

```bash
php artisan octane-doctor:scan --fail-on=high
```

Pest-based CI assertions are optional later.

---

## 19. Readiness Scoring

Readiness scoring is useful for human adoption, but it must not pretend to be scientific.

### Requirements

- clearly presented as heuristic
- based on severity and count weighting
- never replace raw findings
- optional to display

### Recommendation

Include a score only after the underlying findings are trustworthy. For MVP, a summary by severity may be better than a headline score.

---

## 20. MVP Definition

### In scope for MVP

- single package
- core scanner
- scan command
- JSON and terminal output
- config file
- baseline support
- 5 to 7 high-value rules
- strong remediation messages

### Out of scope for MVP

- Rector integration
- dedicated Pest package
- broad ecosystem package compatibility database
- advanced runtime simulation
- memory profiling engine
- Livewire/Filament specialised heuristics
- deep tenant-framework integrations
- fancy IDE integration

---

## 21. Suggested Delivery Phases

### Phase 1 — Core scanner

Deliver:

- finding model
- rule contract
- rule runner
- scan command
- 5 to 7 rules
- readable output
- JSON output
- baseline support

Success criteria:

- can scan a Laravel app and produce useful findings
- findings are accurate enough to be trusted

### Phase 2 — CI ergonomics

Deliver:

- better failure thresholds
- better suppression flow
- improved remediation messages
- optional lightweight Pest hooks if still justified

Success criteria:

- teams can adopt it in CI without major friction

### Phase 3 — Optional extensions

Deliver one of:

- lightweight Pest assertions
- Rector-based safe fixes
- runtime confidence helpers

Success criteria:

- extension adds real value without compromising trust in core scanner

---

## 22. Risks and Constraints

### Risk 1 — Too many false positives

Mitigation:

- conservative rule design
- suppression support
- baseline support
- strong fixture coverage

### Risk 2 — Overpromising Octane safety

Mitigation:

- explicit product language
- risk-focused wording
- documentation that explains boundaries

### Risk 3 — Version support complexity

Mitigation:

- core/adapter split
- narrow public contracts
- matrix testing

### Risk 4 — Unsafe auto-fixes

Mitigation:

- keep auto-fix scope tiny at first
- dry-run by default in examples
- clear skip reasons

### Risk 5 — Legacy app variability

Mitigation:

- custom rule extension points
- configurable ignores and baselines

---

## 23. Open Questions

These should be answered before implementation gets deep.

1. Should the first release include a simple score, or only severity summaries?
2. Which 5 to 7 rules are highest signal for real legacy apps?
3. How much Octane config auditing can we do without becoming noisy or version-fragile?
4. When should Pest integration be introduced, if at all?
5. When should automated fixing be introduced, if at all?

---

## 24. Claude Execution Brief

Build this package as a simple, legacy-friendly Laravel scanner focused on Octane readiness.

Prioritise:

1. core scanner architecture
2. clean rule API
3. high-signal MVP rules
4. human-friendly remediation messages
5. testability across supported Laravel versions
6. low maintenance overhead

Do not optimise for breadth first. Optimise for trust.

Do not introduce package splitting, deep Pest integration, Rector fixing, or runtime simulation in MVP unless there is a very strong reason.

A smaller package with accurate findings is better than a broader package that is harder to trust or maintain.

---

## 25. Initial Acceptance Criteria

The first usable milestone is complete when all of the following are true:

- A Laravel application can install the package cleanly.
- `php artisan octane-doctor:scan` runs successfully.
- At least 5 high-value rules produce useful findings on fixtures.
- Findings include file, severity, explanation, and remediation.
- JSON output is available.
- A baseline file can be generated and respected.
- The package works across the supported Laravel and PHP version matrix.

---

## 26. Suggested Repository Structure

```text
/src
/tests
  /Fixtures
  /Integration
  /Unit
/docs
```

Keep the repository flat and simple in MVP.

---

## 27. Documentation Requirements

At minimum, docs must include:

- what problem the package solves
- what it can and cannot guarantee
- installation
- supported versions
- scan command usage
- CI usage
- baseline usage
- suppression usage
- custom rules
- auto-fix boundaries
- migration examples

---

## 28. Sharp Product Positioning

### Good positioning

- Octane readiness scanner for Laravel
- migration safety tool for legacy Laravel apps moving to Octane
- detect and explain common long-lived worker risks

### Bad positioning

- guaranteed Octane compatibility checker
- magic one-command Octane migration tool
- generic static analysis package with a doctor-themed name but no real Octane focus

---

## 29. Third-Party Leverage Strategy

Keep this simple.

### In MVP

Do not build core functionality on top of third-party Octane packages.

Do not depend on:

- Octane-specific Pest helpers
- Octane health-check packages
- Octane observability packages

### Allowed use

Third-party resources may be used for:

- inspiration for rules
- ideas for documentation
- internal experimentation during development
- companion recommendations in docs

### Reasoning

This package solves a different problem:

- readiness assessment
- migration confidence
- compatibility guardrails

Owning the core keeps maintenance lower and the product clearer.

---

## 30. Final Product Standard

The package should feel like something a pragmatic platform team would trust on a real codebase.

That means:

- accurate enough to be taken seriously
- conservative enough not to mislead teams
- explainable enough to teach developers
- simple enough to maintain
- focused enough to solve the real migration problem

