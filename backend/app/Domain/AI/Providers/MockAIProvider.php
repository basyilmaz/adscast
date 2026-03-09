<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProvider;

class MockAIProvider implements AIProvider
{
    public function generate(string $template, array $context): array
    {
        return [
            'performance_summary' => 'Harcama ve sonuc trendi stabil, CTR tarafinda dikkat gerektiren sinyaller var.',
            'biggest_risk' => 'CTR dususu devam ederse ayni butcede daha az sonuc alinabilir.',
            'biggest_opportunity' => 'Stabil CPA veren kampanyalarda kademeli butce artisi denenebilir.',
            'what_to_test_next' => 'Yeni headline + creative angle testleri',
            'budget_note' => 'Butce artislarini %10-%20 araliginda adimli uygulayin.',
            'creative_note' => 'Frekansi yuksek ad setlerinde creative rotation uygulayin.',
            'targeting_note' => 'Daha dar niyet segmentleri ile LAL varyasyonlarini test edin.',
            'landing_page_note' => 'Landing sayfa hiz ve teklif netligi A/B test edilmeli.',
            'client_friendly_summary' => 'Performans genel olarak iyi; daha iyi sonuc icin kontrollu test ve optimizasyon öneriyoruz.',
            'operator_notes' => 'Rules engine sinyalleri ile uyumlu olarak CTR koruma ve CPA stabilizasyon odakli ilerleyin.',
            'provider' => 'mock',
            'model' => config('services.ai.model', 'mock-model'),
            'template' => $template,
            'context_snapshot' => $context,
        ];
    }
}
