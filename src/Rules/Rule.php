<?php

namespace Geekset\OctaneDoctor\Rules;

use Geekset\OctaneDoctor\Enums\Category;
use Geekset\OctaneDoctor\Enums\Severity;
use Geekset\OctaneDoctor\Finding;
use Geekset\OctaneDoctor\Scanning\ScanContext;

interface Rule
{
    public function id(): string;

    public function title(): string;

    public function severity(): Severity;

    public function category(): Category;

    public function explanation(): RuleExplanation;

    /**
     * @return iterable<Finding>
     */
    public function run(ScanContext $context): iterable;
}
