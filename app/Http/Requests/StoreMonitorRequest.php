<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMonitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'url:http,https', 'unique:monitors,url'],
            'check_interval' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'threshold' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
