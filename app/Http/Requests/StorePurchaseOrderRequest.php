<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'reference' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
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
            'supplier_id.exists' => 'Le fournisseur sélectionné n’existe pas.',
            'reference.max' => 'La référence ne doit pas dépasser 100 caractères.',
            'items.required' => 'Sélectionnez au moins un produit à réceptionner.',
            'items.min' => 'Sélectionnez au moins un produit à réceptionner.',
            'items.*.product_id.exists' => 'L’un des produits sélectionnés n’existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque produit.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
            'items.*.quantity.max' => 'La quantité ne doit pas dépasser 9999999.',
        ];
    }
}
