<?php

namespace App\Http\Requests;

use App\Models\CashSession;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashSessionRequest extends FormRequest
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
            'opening_cash' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * A user cannot open a second session while one is already open.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $openSession = CashSession::query()
                ->where('user_id', $this->user()?->id)
                ->whereNull('closed_at')
                ->exists();

            if ($openSession) {
                $validator->errors()->add(
                    'opening_cash',
                    'Une session est déjà ouverte pour ce compte. Clôturez-la avant d’en ouvrir une nouvelle.',
                );
            }
        });
    }

    /**
     * Get the French validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opening_cash.required' => 'Le fonds de caisse est obligatoire.',
            'opening_cash.numeric' => 'Le fonds de caisse doit être un nombre.',
            'opening_cash.min' => 'Le fonds de caisse doit être positif ou nul.',
            'notes.max' => 'La remarque ne doit pas dépasser 500 caractères.',
        ];
    }
}
