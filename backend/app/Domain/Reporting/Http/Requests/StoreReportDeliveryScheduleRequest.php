<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportDeliveryScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxShareExpiryDays = max((int) config('services.reports.share.max_expiry_days', 30), 1);

        return [
            'report_template_id' => ['required', 'uuid', 'exists:report_templates,id'],
            'recipient_preset_id' => ['nullable', 'uuid'],
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
            'configuration' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
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

            if (! $hasPreset && (! is_array($recipients) || count($recipients) === 0) && (! is_array($contactTags) || count($contactTags) === 0)) {
                $validator->errors()->add('recipients', 'En az bir alici, alici grubu veya kisi etiketi secilmelidir.');
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
