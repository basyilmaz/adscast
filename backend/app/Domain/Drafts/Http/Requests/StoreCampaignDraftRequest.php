<?php

namespace App\Domain\Drafts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampaignDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'meta_ad_account_id' => ['required', 'uuid', 'exists:meta_ad_accounts,id'],
            'objective' => ['required', 'string', 'max:120'],
            'product_service' => ['required', 'string', 'max:2000'],
            'target_audience' => ['required', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:255'],
            'budget_min' => ['nullable', 'numeric', 'min:0'],
            'budget_max' => ['nullable', 'numeric', 'min:0'],
            'offer' => ['nullable', 'string', 'max:2000'],
            'landing_page_url' => ['nullable', 'url', 'max:2048'],
            'tone_style' => ['nullable', 'string', 'max:120'],
            'existing_creative_availability' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
