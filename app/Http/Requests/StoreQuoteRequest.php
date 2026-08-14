<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuoteRequest extends FormRequest
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:50'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'valid_until' => ['nullable', 'date'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
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
            'customer_name.required' => 'Le nom du client est obligatoire.',
            'customer_name.max' => 'Le nom du client ne doit pas dépasser 255 caractères.',
            'customer_email.email' => 'L’adresse e-mail du client n’est pas valide.',
            'customer_phone.max' => 'Le téléphone du client ne doit pas dépasser 50 caractères.',
            'customer_address.max' => 'L’adresse du client ne doit pas dépasser 500 caractères.',
            'valid_until.date' => 'La date de validité est invalide.',
            'discount_percent.numeric' => 'La remise doit être un nombre.',
            'discount_percent.min' => 'La remise doit être positive ou nulle.',
            'discount_percent.max' => 'La remise ne doit pas dépasser 100 %.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
            'items.required' => 'Ajoutez au moins un article au devis.',
            'items.min' => 'Ajoutez au moins un article au devis.',
            'items.*.product_id.exists' => 'L’un des produits sélectionnés n’existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque article.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
            'items.*.unit_price.numeric' => 'Le prix unitaire doit être un nombre.',
            'items.*.unit_price.min' => 'Le prix unitaire doit être positif ou nul.',
        ];
    }
}
