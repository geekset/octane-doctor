<?php

namespace OctaneDoctor\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Foundation\Application;
use OctaneDoctor\Baseline\BaselineRepository;
use OctaneDoctor\Commands\Concerns\RendersTermwind;
use OctaneDoctor\Commands\Concerns\ResolvesScanPaths;
use OctaneDoctor\Scanning\RuleRegistry;
use OctaneDoctor\Scanning\ScanContext;
use OctaneDoctor\Scanning\Scanner;

use function Termwind\render;

/**
 * Snapshots the current scan results to a baseline file. Future runs
 * of octane-doctor:scan will treat every recorded finding as already
 * acknowledged so the exit code only reacts to new findings. The
 * baseline path defaults to the value of octane-doctor.baseline in
 * config and can be overridden with --path.
 */
class BaselineCommand extends Command
{
    use RendersTermwind;
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

        $this->useTermwind();

        $pathInfo = $this->resolvePathInfo();

        $this->renderMissingPathWarnings($pathInfo['missing']);

        $context = new ScanContext($app, $pathInfo['resolved'], $app->basePath());
        $scanner = new Scanner($registry->all());

        $result = $scanner->scan($context);

        $baseline = $repository->save($path, $result->findings);

        $count = $baseline->count();
        $word = $count === 1 ? 'finding' : 'findings';
        $escapedPath = $this->escape($path);

        render(<<<HTML
            <div class="mx-2 my-1">
                <span class="px-1 bg-green-600 text-white font-bold">SAVED</span>
                <span class="ml-1">Recorded {$count} {$word} in baseline at {$escapedPath}.</span>
            </div>
        HTML);

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
