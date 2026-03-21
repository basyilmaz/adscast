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
        return [
            'report_template_id' => ['required', 'uuid', 'exists:report_templates,id'],
            'delivery_channel' => ['nullable', 'string', 'in:email_stub'],
            'cadence' => ['required', 'string', 'in:daily,weekly,monthly'],
            'weekday' => ['nullable', 'integer', 'min:1', 'max:7'],
            'month_day' => ['nullable', 'integer', 'min:1', 'max:28'],
            'send_time' => ['required', 'date_format:H:i'],
            'timezone' => ['nullable', 'timezone:all'],
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*' => ['required', 'email:rfc'],
            'configuration' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cadence = $this->input('cadence');

            if ($cadence === 'weekly' && ! $this->filled('weekday')) {
                $validator->errors()->add('weekday', 'Weekly cadence icin weekday zorunludur.');
            }

            if ($cadence === 'monthly' && ! $this->filled('month_day')) {
                $validator->errors()->add('month_day', 'Monthly cadence icin month_day zorunludur.');
            }
        });
    }
}
