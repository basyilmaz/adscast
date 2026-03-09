<?php

namespace App\Domain\AI\Providers;

use App\Domain\AI\Contracts\AIProvider;

class OpenAIProvider implements AIProvider
{
    public function generate(string $template, array $context): array
    {
        // TODO: OpenAI API entegrasyonu burada uygulanacak.
        // MVP'de fallback olarak mock benzeri deterministic cevap donuluyor.
        return [
            'performance_summary' => 'OpenAI entegrasyonu MVP scaffold modunda, mock output kullanildi.',
            'biggest_risk' => 'Gercek provider baglantisi yapilandirilmadan production kullanimi onerilmez.',
            'biggest_opportunity' => 'Provider baglandiginda daha zengin operator notlari uretilebilir.',
            'what_to_test_next' => 'Provider entegrasyon testleri',
            'budget_note' => 'Butce kararlarini approval akisina bagli tutun.',
            'creative_note' => 'Creative test backlog olusturun.',
            'targeting_note' => 'Segment bazli test plani korunsun.',
            'landing_page_note' => 'Landing deneyleri backlog’a alinmali.',
            'client_friendly_summary' => 'AI ciktisi gecici olarak scaffold modunda uretiliyor.',
            'operator_notes' => 'OpenAIProvider TODO implementasyonu tamamlanmadi.',
            'provider' => 'openai',
            'model' => config('services.ai.model', 'gpt-4.1-mini'),
            'template' => $template,
            'context_snapshot' => $context,
        ];
    }
}
