<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowPortRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public access
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'portNumber' => ['required', 'integer', 'min:1', 'max:65535'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'portNumber.required' => 'Port number is required.',
            'portNumber.integer' => 'Port number must be an integer.',
            'portNumber.min' => 'Port number must be at least 1.',
            'portNumber.max' => 'Port number must not exceed 65535.',
        ];
    }

    /**
     * Get validated data from the route parameters.
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'portNumber' => $this->route('portNumber'),
        ]);
    }
}
