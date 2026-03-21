<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportRecipientPresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'recipients' => ['nullable', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc'],
            'contact_tags' => ['nullable', 'array', 'min:1', 'max:10'],
            'contact_tags.*' => ['required', 'string', 'max:60'],
            'template_kind' => ['nullable', 'string', 'in:client_reporting,stakeholder_update,executive_digest,internal_ops'],
            'target_entity_types' => ['nullable', 'array', 'min:1', 'max:2'],
            'target_entity_types.*' => ['required', 'string', 'in:account,campaign'],
            'matching_companies' => ['nullable', 'array', 'min:1', 'max:10'],
            'matching_companies.*' => ['required', 'string', 'max:120'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:100'],
            'is_recommended_default' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $recipients = $this->input('recipients');
            $contactTags = $this->input('contact_tags');

            if ((! is_array($recipients) || count($recipients) === 0) && (! is_array($contactTags) || count($contactTags) === 0)) {
                $validator->errors()->add('recipients', 'En az bir statik alici veya kisi etiketi gereklidir.');
            }
        });
    }
}
