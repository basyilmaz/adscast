<?php

namespace App\Domain\Rules\Rules;

use App\Domain\Rules\Contracts\Rule;
use App\Domain\Rules\DTO\RuleSignal;

class HighFrequencyRule implements Rule
{
    public function evaluate(array $context): ?RuleSignal
    {
        $frequency = (float) ($context['current']['frequency'] ?? 0);

        if ($frequency < 3) {
            return null;
        }

        return new RuleSignal(
            code: 'high_frequency_fatigue',
            severity: 'medium',
            summary: 'Frekans yuksek, yorgunluk riski var.',
            explanation: 'Ayni kitleye tekrarli gosterim nedeniyle performans dusme riski artiyor.',
            recommendedAction: 'Creative rotation, audience refresh ve caping stratejisi uygulayin.',
            confidence: 0.82,
            entityType: $context['entity_type'],
            entityId: $context['entity_id'],
            dateDetected: $context['date_detected'],
        );
    }
}
