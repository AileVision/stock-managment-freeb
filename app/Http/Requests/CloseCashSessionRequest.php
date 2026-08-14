<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashSessionRequest extends FormRequest
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
            'counted_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'counted_cash.required' => 'Le montant compté est obligatoire.',
            'counted_cash.numeric' => 'Le montant compté doit être un nombre.',
            'counted_cash.min' => 'Le montant compté doit être positif ou nul.',
            'notes.max' => 'La remarque ne doit pas dépasser 500 caractères.',
        ];
    }
}
