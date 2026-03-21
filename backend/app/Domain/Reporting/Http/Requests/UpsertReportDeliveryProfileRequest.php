<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertReportDeliveryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'entity_type' => $this->route('entityType'),
            'entity_id' => $this->route('entityId'),
        ]);
    }

    public function rules(): array
    {
        $maxShareExpiryDays = max((int) config('services.reports.share.max_expiry_days', 30), 1);

        return [
            'entity_type' => ['required', 'string', 'in:account,campaign'],
            'entity_id' => ['required', 'uuid'],
            'recipient_preset_id' => ['nullable', 'uuid'],
            'delivery_channel' => ['required', 'string', 'in:email_stub,email'],
            'cadence' => ['required', 'string', 'in:daily,weekly,monthly'],
            'weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'month_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone:all'],
            'default_range_days' => ['required', 'integer', 'min:1', 'max:90'],
            'layout_preset' => ['nullable', 'string', 'in:standard,client_digest'],
            'recipients' => ['nullable', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc'],
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

            if (! $hasPreset && (! is_array($recipients) || count($recipients) === 0)) {
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
