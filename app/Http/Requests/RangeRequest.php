<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;

class RangeRequest extends FormRequest
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
            'start' => ['required', 'integer', 'min:1', 'max:65535'],
            'end' => ['required', 'integer', 'min:1', 'max:65535', 'gte:start'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'start.required' => 'Start port is required.',
            'start.integer' => 'Start port must be an integer.',
            'start.min' => 'Start port must be at least 1.',
            'start.max' => 'Start port must not exceed 65535.',
            'end.required' => 'End port is required.',
            'end.integer' => 'End port must be an integer.',
            'end.min' => 'End port must be at least 1.',
            'end.max' => 'End port must not exceed 65535.',
            'end.gte' => 'End port must be greater than or equal to start port.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(ValidatorContract $validator): void
    {
        $validator->after(function ($validator) {
            $start = (int) $this->route('start');
            $end = (int) $this->route('end');

            // Validate range size (max 1000 ports inclusive)
            if ($start && $end && ($end - $start + 1) > 1000) {
                $validator->errors()->add(
                    'end',
                    'Port range must not exceed 1000 ports. Please use a smaller range.'
                );
            }
        });
    }

    /**
     * Get validated data from the route parameters.
     */
    public function validationData(): array
    {
        return array_merge($this->all(), [
            'start' => $this->route('start'),
            'end' => $this->route('end'),
        ]);
    }
}
