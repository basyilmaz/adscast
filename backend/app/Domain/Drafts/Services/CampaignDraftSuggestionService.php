<?php

namespace App\Domain\Drafts\Services;

class CampaignDraftSuggestionService
{
    /**
     * @param array<string, mixed> $input
     * @return array<int, array<string, mixed>>
     */
    public function buildSuggestions(array $input): array
    {
        $objective = strtoupper((string) ($input['objective'] ?? 'CONVERSIONS'));
        $product = (string) ($input['product_service'] ?? 'Urun');
        $audience = (string) ($input['target_audience'] ?? 'Genel');
        $location = (string) ($input['location'] ?? 'TR');
        $offer = (string) ($input['offer'] ?? 'Ozel teklif');
        $tone = (string) ($input['tone_style'] ?? 'dogrudan');
        $today = now()->format('Ymd');

        return [
            [
                'item_type' => 'campaign_structure',
                'title' => 'Campaign Structure',
                'content' => [
                    'objective' => $objective,
                    'proposed_campaign_name' => "{$objective}_{$location}_{$today}",
                    'ad_sets' => [
                        "{$location}_CoreAudience",
                        "{$location}_Lookalike",
                    ],
                ],
                'sort_order' => 1,
            ],
            [
                'item_type' => 'audience_suggestions',
                'title' => 'Audience Suggestions',
                'content' => [
                    'primary_audience' => $audience,
                    'secondary_audience' => 'Lookalike 1%-3%',
                    'exclusions' => ['Son 30 gun converter'],
                ],
                'sort_order' => 2,
            ],
            [
                'item_type' => 'budget_suggestions',
                'title' => 'Budget Suggestions',
                'content' => [
                    'daily_budget_range' => [
                        'min' => $input['budget_min'] ?? null,
                        'max' => $input['budget_max'] ?? null,
                    ],
                    'allocation' => [
                        'prospecting' => '70%',
                        'remarketing' => '30%',
                    ],
                ],
                'sort_order' => 3,
            ],
            [
                'item_type' => 'copy_options',
                'title' => 'Copy Suggestions',
                'content' => [
                    'primary_text_options' => [
                        "{$product} ile daha hizli sonuc alin. {$offer}",
                        "{$tone} bir dil ile net fayda anlatimi: {$offer}",
                    ],
                    'headline_options' => [
                        "{$product} icin yeni donem",
                        'Hemen dene, farki gor',
                    ],
                    'description_options' => [
                        'Sinirli sureli teklif',
                        'Hizli kurulum, net fayda',
                    ],
                    'cta' => 'LEARN_MORE',
                ],
                'sort_order' => 4,
            ],
            [
                'item_type' => 'test_plan',
                'title' => 'Test Plan',
                'content' => [
                    'creative_test' => '2 creative angle x 2 headline',
                    'audience_test' => 'Core vs LAL',
                    'landing_test' => 'Hero message A/B',
                    'evaluation_window' => '72 saat',
                ],
                'sort_order' => 5,
            ],
        ];
    }
}
