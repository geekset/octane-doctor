<?php

namespace OctaneDoctor\Ast;

use PhpParser\Error;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

/**
 * Shared AST entry point for rules that inspect source files. Owns
 * the parser instance and the file discovery so individual rules do
 * not duplicate setup, and so the walker can be swapped out in tests
 * with an in-memory implementation.
 */
class FileWalker
{
    protected Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory)->createForHostVersion();
    }

    /**
     * @param  array<int, string>  $paths
     * @return iterable<ParsedFile>
     */
    public function walk(array $paths): iterable
    {
        $existingPaths = array_values(array_filter($paths, fn (string $path) => is_dir($path)));

        if ($existingPaths === []) {
            return;
        }

        $finder = (new Finder)
            ->files()
            ->name('*.php')
            ->ignoreUnreadableDirs()
            ->in($existingPaths);

        foreach ($finder as $file) {
            $contents = @file_get_contents($file->getPathname());

            if ($contents === false) {
                continue;
            }

            try {
                $ast = $this->parser->parse($contents);
            } catch (Error) {
                // Skip files we can't parse; a syntax error in user code is
                // not our problem to surface, and would just generate noise.
                continue;
            }

            if ($ast === null) {
                continue;
            }

            yield new ParsedFile($file, $ast);
        }
    }
}
