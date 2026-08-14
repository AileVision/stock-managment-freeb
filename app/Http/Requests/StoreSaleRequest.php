<?php

namespace App\Http\Requests;

use App\Models\Sale;
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
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'items.required' => 'Le panier est vide.',
            'items.min' => 'Ajoutez au moins un produit au panier.',
            'items.*.product_id.exists' => 'L’un des produits sélectionnés n’existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque produit.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
            'items.*.quantity.max' => 'La quantité ne doit pas dépasser 9999999.',
            'payment_method.required' => 'Veuillez choisir un mode de paiement.',
            'payment_method.in' => 'Le mode de paiement sélectionné n’est pas valide.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
