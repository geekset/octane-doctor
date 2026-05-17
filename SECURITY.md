# Security Policy

## Supported versions

Octane Doctor is in the `0.x` pre-stable line. Only the latest minor receives fixes.

| Version | Supported |
| --- | --- |
| `0.3.x` | Yes |
| `< 0.3` | No |

The supported PHP and Laravel matrix matches Laravel Octane itself: PHP 8.3 or 8.4, Laravel 11.x / 12.x / 13.x.

## Reporting a vulnerability

Do not open a public issue for security problems.

Preferred: open a [private security advisory](https://github.com/geekset/octane-doctor/security/advisories/new) on GitHub.

Fallback: email `iamgayan@gmail.com` with the subject `[octane-doctor security]`.

Include:

* Affected version(s) of `geekset/octane-doctor`.
* A short description of the issue.
* Steps to reproduce, or a proof-of-concept if you have one.
* The impact you expect on a real host application.

## Response

The maintainer acknowledges the report within seven days, validates the issue, and aims to ship a patched release and advisory within thirty days where feasible.

## Scope

In scope:

* Code execution, file disclosure, or privilege escalation triggered by running `octane-doctor:scan`, `octane-doctor:baseline`, or `octane-doctor:explain` on a host application.
* Vulnerabilities in the package's own dependencies that the scanner pins or exposes.

Out of scope:

* Bugs in scanned host applications. Octane Doctor reports patterns; it does not modify host code.
* Issues that require an attacker to already have write access to the host application's `vendor/` directory or `composer.json`.
* False positives or missed detections. Report those as normal issues.
