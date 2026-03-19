<?php

namespace App\Domain\Meta\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ExchangeMetaOAuthCodeRequest extends FormRequest
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
            'code' => ['required', 'string', 'min:5', 'max:4096'],
            'state' => ['required', 'string', 'min:20', 'max:191'],
        ];
    }
}
