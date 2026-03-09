<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class FallingCtrRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $current = (float) ($context['current']['ctr'] ?? 0);
        $previous = (float) ($context['previous']['ctr'] ?? 0);

        if ($previous <= 0 || $current >= ($previous * 0.8)) {
            return null;
        }

        return new RuleSignal(
            code: 'falling_ctr',
            severity: 'medium',
            summary: 'CTR dusus trendinde.',
            explanation: 'Ilgi sinyali zayifliyor, creative yorgunlugu veya mesaj uyumsuzlugu olabilir.',
            recommendedAction: 'Yeni hook/headline varyasyonlariyla creative test plani acin.',
            confidence: 0.8,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
