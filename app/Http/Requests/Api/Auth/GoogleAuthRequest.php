<?php

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;

class GoogleAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'success' => false,
            'errors' => $validator->errors(),
            'message' => __('errors.validation_failed'),
        ], 422);

        throw new ValidationException($validator, $response);
    }

    public function rules(): array
    {
        return [
            'id_token' => ['required', 'string'],
        ];
    }
}
