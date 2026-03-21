<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'entity_type' => ['required', 'string', 'in:account,campaign'],
            'entity_id' => ['required', 'uuid'],
            'report_type' => ['required', 'string', 'in:client_account_summary_v1,client_campaign_summary_v1'],
            'default_range_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'layout_preset' => ['nullable', 'string', 'max:80'],
            'configuration' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
