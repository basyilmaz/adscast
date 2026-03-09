<?php

namespace App\Domain\Rules\Contracts;

use App\Domain\Rules\DTO\RuleSignal;

interface Rule
{
    /**
     * @param array<string, mixed> $context
     */
    public function evaluate(array $context): ?RuleSignal;
}
