<?php

namespace OctaneDoctor\Rules;

use OctaneDoctor\Enums\Category;
use OctaneDoctor\Enums\RiskClass;
use OctaneDoctor\Enums\Severity;
use OctaneDoctor\Finding;
use OctaneDoctor\Scanning\ScanContext;

interface Rule
{
    public function id(): string;

    public function title(): string;

    public function severity(): Severity;

    public function category(): Category;

    public function riskClass(): RiskClass;

    public function explanation(): RuleExplanation;

    /**
     * @return iterable<Finding>
     */
    public function run(ScanContext $context): iterable;
}
