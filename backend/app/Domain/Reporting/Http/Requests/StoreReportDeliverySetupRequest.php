<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportDeliverySetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $recipients = $this->input('recipients');
        $contactTags = $this->input('contact_tags');

        $this->merge([
            'recipients' => is_array($recipients) && count($recipients) === 0 ? null : $recipients,
            'contact_tags' => is_array($contactTags) && count($contactTags) === 0 ? null : $contactTags,
        ]);
    }

    public function rules(): array
    {
        $maxShareExpiryDays = max((int) config('services.reports.share.max_expiry_days', 30), 1);

        return [
            'entity_type' => ['required', 'string', 'in:account,campaign'],
            'entity_id' => ['required', 'uuid'],
            'recipient_preset_id' => ['nullable', 'uuid'],
            'template_name' => ['nullable', 'string', 'max:160'],
            'default_range_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'layout_preset' => ['nullable', 'string', 'in:standard,client_digest'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_channel' => ['nullable', 'string', 'in:email_stub,email'],
            'cadence' => ['required', 'string', 'in:daily,weekly,monthly'],
            'weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'month_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone:all'],
            'recipients' => ['nullable', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc'],
            'contact_tags' => ['nullable', 'array', 'min:1', 'max:10'],
            'contact_tags.*' => ['required', 'string', 'max:60'],
            'recipient_group_selection' => ['nullable', 'array'],
            'recipient_group_selection.id' => ['nullable', 'string', 'max:160'],
            'recipient_group_selection.source_type' => ['nullable', 'string', 'in:preset,segment,smart,manual'],
            'recipient_group_selection.source_subtype' => ['nullable', 'string', 'max:80'],
            'recipient_group_selection.source_id' => ['nullable', 'string', 'max:160'],
            'recipient_group_selection.name' => ['nullable', 'string', 'max:160'],
            'recommended_recipient_group' => ['nullable', 'array'],
            'recommended_recipient_group.id' => ['nullable', 'string', 'max:160'],
            'recommended_recipient_group.source_type' => ['nullable', 'string', 'in:preset,segment,smart,manual'],
            'recommended_recipient_group.source_subtype' => ['nullable', 'string', 'max:80'],
            'recommended_recipient_group.source_id' => ['nullable', 'string', 'max:160'],
            'recommended_recipient_group.name' => ['nullable', 'string', 'max:160'],
            'is_active' => ['nullable', 'boolean'],
            'save_as_default_profile' => ['nullable', 'boolean'],
            'auto_share_enabled' => ['nullable', 'boolean'],
            'share_label_template' => ['nullable', 'string', 'max:160'],
            'share_expires_in_days' => ['nullable', 'integer', 'min:1', "max:{$maxShareExpiryDays}"],
            'share_allow_csv_download' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cadence = $this->input('cadence');
            $hasPreset = $this->filled('recipient_preset_id');
            $recipients = $this->input('recipients');
            $contactTags = $this->input('contact_tags');

            if (
                ! $hasPreset
                && (! is_array($recipients) || count($recipients) === 0)
                && (! is_array($contactTags) || count($contactTags) === 0)
            ) {
                $validator->errors()->add('recipients', 'En az bir alici veya kayitli alici listesi secilmelidir.');
            }

            if ($cadence === 'weekly' && ! $this->filled('weekday')) {
                $validator->errors()->add('weekday', 'Weekly cadence icin weekday zorunludur.');
            }

            if ($cadence === 'monthly' && ! $this->filled('month_day')) {
                $validator->errors()->add('month_day', 'Monthly cadence icin month_day zorunludur.');
            }
        });
    }
}
