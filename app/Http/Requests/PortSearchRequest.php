<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PortSearchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public search
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'type' => ['nullable', 'string', 'in:all,port,error,extension'],
            'protocol' => ['nullable', 'string', 'in:TCP,UDP,SCTP'],
            'risk_level' => ['nullable', 'string', 'in:High,Medium,Low'],
            'category' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Please enter a search query.',
            'q.min' => 'Search query must be at least 2 characters.',
            'q.max' => 'Search query must not exceed 100 characters.',
            'protocol.in' => 'Protocol must be one of: TCP, UDP, SCTP.',
            'risk_level.in' => 'Risk level must be one of: High, Medium, Low.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize the search query
        if ($this->has('q')) {
            $this->merge([
                'q' => strip_tags($this->input('q')),
            ]);
        }
    }
}
