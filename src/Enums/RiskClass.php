<?php

namespace OctaneDoctor\Enums;

/*
 * The realised-risk axis. Independent of `Category`, which groups
 * rules by the subject area they inspect (request state, container
 * lifecycle, etc.). A RiskClass answers "what kind of damage if this
 * rule is violated under a long-lived worker", which is how project
 * planners and QA reason about Octane adoption.
 */
enum RiskClass: string
{
    case DataLeak = 'data-leak';
    case MemoryLeak = 'memory-leak';
    case RequestScopeMisuse = 'request-scope-misuse';
    case Deprecation = 'deprecation';
    case Performance = 'performance';
}
