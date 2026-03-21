<?php

namespace App\Domain\Reporting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveReportRecipientGroupSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'in:account,campaign'],
            'entity_id' => ['required', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:8'],
        ];
    }
}
