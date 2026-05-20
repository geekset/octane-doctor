<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use OctaneDoctor\Baseline\BaselineRepository;
use OctaneDoctor\Commands\Concerns\ResolvesScanPaths;
use OctaneDoctor\Scanning\RuleRegistry;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Scanning\Scanner;

/**
 * Snapshots the current scan results to a baseline file. Future runs
 * of octane-doctor:scan will treat every recorded finding as already
 * acknowledged so the exit code only reacts to new findings. The
 * baseline path defaults to the value of octane-doctor.baseline in
 * config and can be overridden with --path.
 */
class BaselineCommand extends Command
{
    use ResolvesScanPaths;

    public $signature = 'octane-doctor:baseline
        {--path= : Override the baseline file path}';

    public $description = 'Snapshot the current findings into a baseline file.';

    public function handle(Application $app, RuleRegistry $registry, BaselineRepository $repository): int
    {
        $path = $this->resolvePath();

        if ($path === null) {
            $this->error('No baseline path is configured. Set octane-doctor.baseline or pass --path.');

            return self::FAILURE;
        }

        $pathInfo = $this->resolvePathInfo();

        foreach ($pathInfo['missing'] as $missing) {
            $this->warn("WARNING: configured scan path does not exist: {$missing}");
        }

        $context = new ScanContext($app, $pathInfo['resolved'], $app->basePath());
        $scanner = new Scanner($registry->all());

        $result = $scanner->scan($context);

        $baseline = $repository->save($path, $result->findings);

        $this->info(sprintf(
            'Recorded %d finding%s in baseline at %s.',
            $baseline->count(),
            $baseline->count() === 1 ? '' : 's',
            $path,
        ));

        return self::SUCCESS;
    }

    protected function resolvePath(): ?string
    {
        $option = $this->option('path');

        if (is_string($option) && $option !== '') {
            return $option;
        }

        $configured = config('octane-doctor.baseline');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return null;
    }
}
