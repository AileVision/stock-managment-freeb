<?php

namespace App\Http\Requests;

use App\Models\CashSession;
use App\Models\Sale;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'payment_method' => ['required', Rule::in(array_keys(Sale::PAYMENT_METHODS))],
            'session_id' => ['nullable', 'exists:cash_sessions,id'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'tax_rate_id' => ['nullable', 'exists:tax_rates,id'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'mobile_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The referenced session must still be open and mobile payments require
     * a phone number.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $session = CashSession::query()->whereKey((int) $this->input('session_id'))->first();

            if ($session !== null && ! $session->isOpen()) {
                $validator->errors()->add('session_id', 'La session de caisse est fermée.');
            }

            if (in_array($this->input('payment_method'), Sale::MOBILE_PAYMENT_METHODS, true)) {
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
            'items.required' => 'Le panier est vide.',
            'items.min' => 'Ajoutez au moins un produit au panier.',
            'items.*.product_id.exists' => 'L’un des produits sélectionnés n’existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque produit.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
            'items.*.quantity.max' => 'La quantité ne doit pas dépasser 9999999.',
            'payment_method.required' => 'Veuillez choisir un mode de paiement.',
            'payment_method.in' => 'Le mode de paiement sélectionné n’est pas valide.',
            'warehouse_id.exists' => 'L’entrepôt sélectionné n’existe pas.',
            'tax_rate_id.exists' => 'Le taux de TVA sélectionné n’existe pas.',
            'mobile_phone.max' => 'Le numéro de téléphone ne doit pas dépasser 20 caractères.',
            'mobile_reference.max' => 'La référence de transaction ne doit pas dépasser 100 caractères.',
            'discount_percent.numeric' => 'La remise doit être un nombre.',
            'discount_percent.min' => 'La remise doit être positive ou nulle.',
            'discount_percent.max' => 'La remise ne doit pas dépasser 100 %.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
