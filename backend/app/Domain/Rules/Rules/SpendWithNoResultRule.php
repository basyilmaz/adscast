<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class SpendWithNoResultRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $spend = (float) ($context['current']['spend'] ?? 0);
        $results = (float) ($context['current']['results'] ?? 0);

        if ($spend < 50 || $results > 0) {
            return null;
        }

        return new RuleSignal(
            code: 'spend_no_result',
            severity: 'high',
            summary: 'Harcama var ancak sonuc yok.',
            explanation: 'Kampanya son periyotta harcama yapmasina ragmen sonuc uretmiyor.',
            recommendedAction: 'Kitle, creative ve landing page uyumunu inceleyip butceyi gecici olarak kisitlayin.',
            confidence: 0.92,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
