<?php

namespace Geekset\OctaneDoctor\Ast;

use PhpParser\Node;
use SplFileInfo;

final readonly class ParsedFile
{
    /**
     * @param  array<int, Node>  $ast
     */
    public function __construct(
        public SplFileInfo $file,
        public array $ast,
    ) {}

    public function path(): string
    {
        return $this->file->getPathname();
    }
}
