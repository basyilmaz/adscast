<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class RisingCpmRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $current = (float) ($context['current']['cpm'] ?? 0);
        $previous = (float) ($context['previous']['cpm'] ?? 0);

        if ($previous <= 0 || $current <= ($previous * 1.2)) {
            return null;
        }

        return new RuleSignal(
            code: 'rising_cpm',
            severity: 'medium',
            summary: 'CPM yukselis baskisi var.',
            explanation: 'Aynı kitleye ulasim maliyeti onceki doneme gore anlamli arttı.',
            recommendedAction: 'Kitle genisletme ve placement cesitlendirme deneyin.',
            confidence: 0.78,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
