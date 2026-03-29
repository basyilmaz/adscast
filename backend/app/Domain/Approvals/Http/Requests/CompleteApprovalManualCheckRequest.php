<?php

namespace App\Domain\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteApprovalManualCheckRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
