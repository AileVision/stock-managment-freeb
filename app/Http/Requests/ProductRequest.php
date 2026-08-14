<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
        $product = $this->route('product');
        $productId = $product instanceof Model ? $product->getKey() : null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'unit' => ['required', Rule::in(array_keys(Product::UNITS))],
            'purchase_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'min_stock' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'description' => ['nullable', 'string', 'max:2000'],
        ];

        // Le stock initial n'est accepté qu'à la création d'un produit.
        if ($this->isMethod('post')) {
            $rules['stock_quantity'] = ['nullable', 'integer', 'min:0', 'max:9999999'];
        }

        return $rules;
    }

    /**
     * Get the French validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom du produit est obligatoire.',
            'name.max' => 'Le nom du produit ne doit pas dépasser 255 caractères.',
            'sku.required' => 'La référence (SKU) est obligatoire.',
            'sku.unique' => 'Cette référence (SKU) est déjà utilisée par un autre produit.',
            'sku.max' => 'La référence (SKU) ne doit pas dépasser 50 caractères.',
            'barcode.max' => 'Le code-barres ne doit pas dépasser 100 caractères.',
            'category_id.exists' => 'La catégorie sélectionnée n’existe pas.',
            'supplier_id.exists' => 'Le fournisseur sélectionné n’existe pas.',
            'unit.required' => 'L’unité de mesure est obligatoire.',
            'unit.in' => 'L’unité de mesure sélectionnée n’est pas valide.',
            'purchase_price.required' => 'Le prix d’achat est obligatoire.',
            'purchase_price.numeric' => 'Le prix d’achat doit être un nombre.',
            'purchase_price.min' => 'Le prix d’achat doit être positif ou nul.',
            'sale_price.required' => 'Le prix de vente est obligatoire.',
            'sale_price.numeric' => 'Le prix de vente doit être un nombre.',
            'sale_price.min' => 'Le prix de vente doit être positif ou nul.',
            'stock_quantity.integer' => 'Le stock initial doit être un nombre entier.',
            'stock_quantity.min' => 'Le stock initial doit être positif ou nul.',
            'min_stock.integer' => 'Le seuil d’alerte doit être un nombre entier.',
            'min_stock.min' => 'Le seuil d’alerte doit être positif ou nul.',
            'description.max' => 'La description ne doit pas dépasser 2000 caractères.',
        ];
    }
}
