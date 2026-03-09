<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class RisingCpaRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $current = (float) ($context['current']['cpa_cpl'] ?? 0);
        $previous = (float) ($context['previous']['cpa_cpl'] ?? 0);

        if ($previous <= 0 || $current <= ($previous * 1.2)) {
            return null;
        }

        return new RuleSignal(
            code: 'rising_cpa',
            severity: 'high',
            summary: 'CPA/CPL hizli sekilde yukseliyor.',
            explanation: 'Maliyet verimliligi onceki doneme gore anlamli seviyede bozuldu.',
            recommendedAction: 'Dusuk performansli ad setleri azaltip yuksek niyetli segmentlerde yeni test acin.',
            confidence: 0.86,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
