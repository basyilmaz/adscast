<?php

namespace App\Domain\Tenants\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'workspace_id' => ['required', 'uuid', 'exists:workspaces,id'],
        ];
    }
}
