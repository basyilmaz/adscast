<?php

namespace App\Domain\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveApprovalRemediationAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'window_days' => ['nullable', 'integer', Rule::in([7, 30, 90])],
        ];
    }
}
