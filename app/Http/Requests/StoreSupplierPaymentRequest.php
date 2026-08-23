<?php

namespace App\Http\Requests;

use App\Models\SupplierPayment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierPaymentRequest extends FormRequest
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
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(SupplierPayment::PAYMENT_METHODS))],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'mobile_reference' => ['nullable', 'string', 'max:100'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The purchase order must have a remaining balance and mobile payments
     * require a phone number.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (in_array($this->input('payment_method'), SupplierPayment::MOBILE_PAYMENT_METHODS, true)) {
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
            'purchase_order_id.required' => 'La commande est obligatoire.',
            'purchase_order_id.exists' => 'La commande sélectionnée n\'existe pas.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'payment_method.required' => 'Choisissez le mode de paiement.',
            'payment_method.in' => 'Le mode de paiement sélectionné n\'est pas valide.',
            'mobile_phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'reference.max' => 'La référence ne doit pas dépasser 100 caractères.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
