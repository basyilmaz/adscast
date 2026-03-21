<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleReportDeliveryProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
