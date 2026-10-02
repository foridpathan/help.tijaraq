<?php

namespace App\Integrations\Tijaraq\Http\Requests;

use App\Integrations\Tijaraq\Exceptions\IntegrationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class IntegrationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // authentication is done by the HMAC middleware
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw IntegrationException::validation(
            'The request failed validation.',
            $validator->errors()->toArray(),
        );
    }
}
