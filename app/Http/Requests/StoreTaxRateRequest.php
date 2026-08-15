<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the French validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du taux est obligatoire.',
            'name.max' => 'Le nom ne doit pas dépasser 100 caractères.',
            'rate.required' => 'Le taux est obligatoire.',
            'rate.numeric' => 'Le taux doit être un nombre.',
            'rate.min' => 'Le taux doit être positif ou nul.',
            'rate.max' => 'Le taux ne doit pas dépasser 100 %.',
        ];
    }
}
