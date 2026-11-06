<?php

namespace App\Http\Requests\Api;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class StoreLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination_url' => ['required', 'string', 'max:2000', 'url:http,https'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'custom_alias' => [
                'bail',
                'nullable',
                'string',
                'regex:/\A[A-Za-z0-9_-]{3,32}\z/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (in_array(strtolower($value), ['api', 'admin', 'up', 'instance'], true)) {
                        $fail('This alias is reserved.');
                    }
                },
            ],
        ];
    }
}
