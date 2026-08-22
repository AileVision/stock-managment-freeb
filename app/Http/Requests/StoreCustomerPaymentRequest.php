<?php

namespace App\Http\Requests;

use App\Models\CustomerPayment;
use App\Models\Sale;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerPaymentRequest extends FormRequest
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
            'sale_id' => ['required', 'exists:sales,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(CustomerPayment::PAYMENT_METHODS))],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'mobile_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The sale must be an open credit sale, the amount cannot exceed the
     * remaining balance, mobile payments require a phone number and an avoir
     * payment requires available avoirs.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sale = Sale::query()->with('payments')->find((int) $this->input('sale_id'));

            if ($sale === null) {
                return;
            }

            if ($sale->payment_method !== 'credit' || $sale->status !== Sale::STATUS_COMPLETED) {
                $validator->errors()->add('sale_id', 'Cette vente ne peut pas recevoir d’encaissement.');

                return;
            }

            $amount = (float) $this->input('amount');
            $balance = $sale->balance();

            if ($amount > $balance + 0.01) {
                $validator->errors()->add('amount', "Le montant dépasse le solde restant de {$balance} FCFA.");
            }

            if (in_array($this->input('payment_method'), Sale::MOBILE_PAYMENT_METHODS, true)) {
                $phone = trim((string) $this->input('mobile_phone'));

                if ($phone === '') {
                    $validator->errors()->add('mobile_phone', 'Le numéro de téléphone mobile est obligatoire pour ce mode de paiement.');
                } elseif (preg_match('/^[0-9+ ]{6,20}$/', $phone) !== 1) {
                    $validator->errors()->add('mobile_phone', 'Le numéro de téléphone mobile est invalide.');
                }
            }

            if ($this->input('payment_method') === 'avoir') {
                $customer = $sale->customer()->with('creditNotes')->first();

                if ($customer === null) {
                    $validator->errors()->add('payment_method', 'La vente n’a pas de client rattaché.');

                    return;
                }

                $available = $customer->availableAvoirs();

                if ($available < $amount - 0.01) {
                    $validator->errors()->add('payment_method', "Avoirs disponibles insuffisants ({$available} FCFA).");
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
            'sale_id.required' => 'La vente est obligatoire.',
            'sale_id.exists' => 'La vente sélectionnée n’existe pas.',
            'amount.required' => 'Le montant est obligatoire.',
            'amount.numeric' => 'Le montant doit être un nombre.',
            'amount.min' => 'Le montant doit être supérieur à zéro.',
            'payment_method.required' => 'Choisissez le mode de paiement.',
            'payment_method.in' => 'Le mode de paiement n’est pas valide.',
            'mobile_phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'mobile_reference.max' => 'La référence ne doit pas dépasser 100 caractères.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
