<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
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
            'product_id' => ['required', 'exists:products,id'],
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The source warehouse must hold enough stock.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $product = Product::query()->whereKey((int) $this->input('product_id'))->first();
            $fromWarehouseId = (int) $this->input('from_warehouse_id');
            $quantity = (int) $this->input('quantity');

            if ($product === null || $fromWarehouseId === 0) {
                return;
            }

            $available = $product->stockAt($fromWarehouseId);

            if ($available < $quantity) {
                $validator->errors()->add(
                    'quantity',
                    "Stock insuffisant dans l’entrepôt de départ. Quantité disponible : {$available}.",
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
            'product_id.required' => 'Veuillez sélectionner un produit.',
            'product_id.exists' => 'Le produit sélectionné n’existe pas.',
            'from_warehouse_id.required' => 'Choisissez l’entrepôt de départ.',
            'from_warehouse_id.exists' => 'L’entrepôt de départ n’existe pas.',
            'to_warehouse_id.required' => 'Choisissez l’entrepôt de destination.',
            'to_warehouse_id.exists' => 'L’entrepôt de destination n’existe pas.',
            'to_warehouse_id.different' => 'Les deux entrepôts doivent être différents.',
            'quantity.required' => 'La quantité est obligatoire.',
            'quantity.integer' => 'La quantité doit être un nombre entier.',
            'quantity.min' => 'La quantité doit être supérieure à zéro.',
            'quantity.max' => 'La quantité ne doit pas dépasser 9999999.',
            'notes.max' => 'La remarque ne doit pas dépasser 500 caractères.',
        ];
    }
}
