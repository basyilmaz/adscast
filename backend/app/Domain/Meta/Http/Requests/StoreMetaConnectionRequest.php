<?php

namespace App\Domain\Meta\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMetaConnectionRequest extends FormRequest
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
            'access_token' => ['required', 'string', 'min:20'],
            'refresh_token' => ['nullable', 'string', 'min:20'],
            'token_expires_at' => ['nullable', 'date'],
            'external_user_id' => ['nullable', 'string', 'max:191'],
            'api_version' => ['nullable', 'string', 'max:20'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
