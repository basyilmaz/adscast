<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReportContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email:rfc', 'max:160'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'role_label' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:10'],
            'tags.*' => ['required', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
