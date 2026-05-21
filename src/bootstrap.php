<?php

/*
 * Early STDERR redirect for `octane-doctor:scan --format=json`.
 *
 * When the user pipes the JSON payload into `jq` or any CI tool,
 * anything PHP writes to STDOUT before the JSON document corrupts
 * the pipeline. Host applications routinely trigger deprecation
 * notices while loading their own config files (passport.php is a
 * common offender with `base64_decode(null)` warnings), and those
 * notices fire *before* Laravel's service providers boot. Setting
 * `display_errors` inside our service provider is too late to
 * catch them.
 *
 * Composer's `autoload.files` directive is the earliest hook the
 * package controls: it runs the moment vendor/autoload.php is
 * required, which happens before Laravel parses any host config.
 * Redirecting at that point keeps the JSON payload clean without
 * touching anything else on non-scan invocations.
 */

if (PHP_SAPI !== 'cli') {
    return;
}

$argv = $_SERVER['argv'] ?? null;

if (! is_array($argv)) {
    return;
}

$hasScanCommand = false;
$hasJsonFormat = false;

foreach ($argv as $index => $argument) {
    if (! is_string($argument)) {
        continue;
    }

    if ($argument === 'octane-doctor:scan') {
        $hasScanCommand = true;

        continue;
    }

    if ($argument === '--format=json') {
        $hasJsonFormat = true;

        continue;
    }

    if ($argument === '--format' && ($argv[$index + 1] ?? null) === 'json') {
        $hasJsonFormat = true;
    }
}

if ($hasScanCommand && $hasJsonFormat) {
    ini_set('display_errors', 'stderr');
}
