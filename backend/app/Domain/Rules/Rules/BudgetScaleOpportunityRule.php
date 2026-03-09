<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class BudgetScaleOpportunityRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $results = (float) ($context['current']['results'] ?? 0);
        $currentCpa = (float) ($context['current']['cpa_cpl'] ?? 0);
        $previousCpa = (float) ($context['previous']['cpa_cpl'] ?? 0);
        $ctr = (float) ($context['current']['ctr'] ?? 0);

        $stableCpa = $previousCpa > 0 ? $currentCpa <= ($previousCpa * 1.05) : $currentCpa > 0;
        $healthyCtr = $ctr >= 1.2;

        if (! ($results >= 10 && $stableCpa && $healthyCtr)) {
            return null;
        }

        return new RuleSignal(
            code: 'budget_scale_opportunity',
            severity: 'low',
            summary: 'Butce artisi icin firsat gorunuyor.',
            explanation: 'Son donemde yeterli sonuc ve istikrarlı verimlilik sinyali var.',
            recommendedAction: 'Butceyi kademeli (%10-%20) arttirip 48 saat kaliteyi takip edin.',
            confidence: 0.76,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
