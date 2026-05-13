<?php

namespace Geekset\OctaneDoctor\Enums;

enum Severity: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';

    public function isAtLeast(self $threshold): bool
    {
        return $this->weight() >= $threshold->weight();
    }

    public function weight(): int
    {
        return match ($this) {
            self::High => 30,
            self::Medium => 20,
            self::Low => 10,
            self::Info => 0,
        };
    }
}
