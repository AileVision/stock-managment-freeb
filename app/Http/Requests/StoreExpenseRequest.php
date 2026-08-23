<?php

namespace App\Http\Requests;

use App\Models\Expense;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
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
            'category' => ['required', 'string', Rule::in(array_keys(Expense::CATEGORIES))],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::PAYMENT_METHODS))],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'mobile_reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Mobile payments require a phone number.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (in_array($this->input('payment_method'), Expense::MOBILE_PAYMENT_METHODS, true)) {
                $phone = trim((string) $this->input('mobile_phone'));

                if ($phone === '') {
                    $validator->errors()->add('mobile_phone', 'Le numéro de téléphone mobile est obligatoire pour ce mode de paiement.');
                } elseif (preg_match('/^[0-9+ ]{6,20}$/', $phone) !== 1) {
                    $validator->errors()->add('mobile_phone', 'Le numéro de téléphone mobile est invalide.');
                }
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
            'category.required' => 'La catégorie est obligatoire.',
            'category.in' => 'La catégorie sélectionnée n\'est pas valide.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'amount.max' => 'Le montant ne doit pas dépasser 99 999 999,99.',
            'payment_method.required' => 'Choisissez le mode de paiement.',
            'payment_method.in' => 'Le mode de paiement sélectionné n\'est pas valide.',
            'expense_date.required' => 'La date de la dépense est obligatoire.',
            'expense_date.date' => 'La date de la dépense n\'est pas valide.',
            'expense_date.before_or_equal' => 'La date de la dépense ne peut pas être dans le futur.',
            'description.max' => 'La description ne doit pas dépasser 255 caractères.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
            'mobile_phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'mobile_reference.max' => 'La référence ne doit pas dépasser 100 caractères.',
        ];
    }
}
