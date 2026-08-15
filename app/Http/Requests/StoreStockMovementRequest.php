<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
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
            'type' => ['required', Rule::in(['in', 'out', 'adjustment'])],
            'quantity' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ];
    }

    /**
     * Add domain rules: sign of the quantity and available stock.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('type');
            $quantity = (int) $this->input('quantity');

            if (in_array($type, ['in', 'out'], true) && $quantity <= 0) {
                $validator->errors()->add('quantity', 'La quantité doit être un nombre entier positif.');

                return;
            }

            if ($type === 'adjustment' && $quantity === 0) {
                $validator->errors()->add('quantity', 'La quantité d’ajustement doit être différente de zéro.');

                return;
            }

            $product = Product::query()->whereKey((int) $this->input('product_id'))->first();

            if ($type === 'out' && $product !== null) {
                $warehouseId = (int) ($this->input('warehouse_id') ?? Warehouse::defaultId() ?? 0);
                $available = $warehouseId > 0 ? $product->stockAt($warehouseId) : (int) $product->stock_quantity;

                if ($available < $quantity) {
                    $validator->errors()->add(
                        'quantity',
                        "Stock insuffisant. Quantité disponible : {$available}."
                    );
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
            'product_id.required' => 'Veuillez sélectionner un produit.',
            'product_id.exists' => 'Le produit sélectionné n’existe pas.',
            'type.required' => 'Veuillez choisir un type de mouvement.',
            'type.in' => 'Le type de mouvement sélectionné n’est pas valide.',
            'quantity.required' => 'La quantité est obligatoire.',
            'quantity.integer' => 'La quantité doit être un nombre entier.',
            'reason.required' => 'Le motif est obligatoire.',
            'reason.max' => 'Le motif ne doit pas dépasser 255 caractères.',
            'reference.max' => 'La référence ne doit pas dépasser 100 caractères.',
            'warehouse_id.exists' => 'L’entrepôt sélectionné n’existe pas.',
        ];
    }
}
