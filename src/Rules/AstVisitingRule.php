<?php

namespace OctaneDoctor\Rules;

use OctaneDoctor\Ast\ParsedFile;
use OctaneDoctor\Finding;
use PhpParser\NodeVisitor;

/**
 * Marker interface implemented by rules that inspect parsed PHP
 * files. When a rule implements this contract, the Scanner skips
 * the rule's own file-walking loop in run() and instead drives the
 * walk itself: every file is parsed once, every AST rule's visitor
 * runs inside a single NodeTraverser pass, and findings are drained
 * from each rule afterwards.
 *
 * AST rules still implement run() so they remain usable in
 * isolation (custom tooling, tests that exercise one rule against
 * a fixture directory), but the Scanner's optimised path is
 * preferred whenever multiple AST rules are registered.
 */
interface AstVisitingRule extends Rule
{
    /**
     * Build a fresh NodeVisitor for the given parsed file. Each
     * invocation must return a new instance because the visitor is
     * expected to accumulate per-file state (matched hits) that
     * findingsFor() will drain.
     */
    public function buildVisitor(ParsedFile $parsed): NodeVisitor;

    /**
     * Convert the state accumulated by the visitor into findings.
     *
     * @return iterable<Finding>
     */
    public function findingsFor(ParsedFile $parsed, NodeVisitor $visitor): iterable;
}
