<?php

namespace OctaneDoctor\Tests\Support;

use OctaneDoctor\Ast\FileWalker;
use OctaneDoctor\Ast\ParsedFile;

/**
 * Test double that records how many times the walker is invoked and
 * how many parsed files it yields. Used to prove the Scanner walks
 * each path set exactly once across all AST rules.
 */
class CountingFileWalker extends FileWalker
{
    public int $walkCalls = 0;

    public int $yieldedFiles = 0;

    /**
     * @param  array<int, string>  $paths
     * @return iterable<ParsedFile>
     */
    public function walk(array $paths): iterable
    {
        $this->walkCalls++;

        foreach (parent::walk($paths) as $parsed) {
            $this->yieldedFiles++;
            yield $parsed;
        }
    }
}
