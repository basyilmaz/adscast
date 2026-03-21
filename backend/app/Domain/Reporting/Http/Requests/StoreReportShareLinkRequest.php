<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportShareLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['nullable', 'string', 'max:160'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'allow_csv_download' => ['nullable', 'boolean'],
        ];
    }
}
