<?php

namespace App\Http\Requests;

use App\Models\Sale;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReturnRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999999'],
            // Sort du retour : remboursement ou avoir.
            'disposition' => ['required', Rule::in(['refund', 'avoir'])],
            'refund_method' => ['required_if:disposition,refund', Rule::in(['cash', 'card', 'transfer', 'tmoney', 'flooz'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The sale must be completed and the returned quantities cannot exceed
     * what was actually sold (minus what was already returned).
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sale = Sale::query()
                ->with(['items', 'items.product:id,name'])
                ->find((int) $this->input('sale_id'));

            if ($sale === null) {
                return;
            }

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                $validator->errors()->add('sale_id', 'Cette vente est annulée, elle ne peut pas être retournée.');

                return;
            }

            foreach ((array) $this->input('items') as $index => $item) {
                $saleItem = $sale->items->firstWhere('product_id', (int) ($item['product_id'] ?? 0));

                if ($saleItem === null) {
                    $validator->errors()->add("items.{$index}.product_id", 'Ce produit ne fait pas partie de la vente.');

                    continue;
                }

                $available = $saleItem->quantity - $saleItem->returned_quantity;

                if ((int) ($item['quantity'] ?? 0) > $available) {
                    $name = $saleItem->product->name ?? 'produit';

                    $validator->errors()->add("items.{$index}.quantity", "Quantité retournable pour « {$name} » : {$available}.");
                }
            }

            if ((int) collect($this->input('items'))->sum('quantity') <= 0) {
                $validator->errors()->add('items', 'Retournez au moins un article.');
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
            'sale_id.required' => 'Choisissez la vente à retourner.',
            'sale_id.exists' => 'La vente sélectionnée n’existe pas.',
            'items.required' => 'Sélectionnez les articles à retourner.',
            'items.min' => 'Sélectionnez au moins un article.',
            'items.*.product_id.exists' => 'L’un des produits sélectionnés n’existe pas.',
            'items.*.quantity.required' => 'La quantité est obligatoire pour chaque article.',
            'items.*.quantity.integer' => 'La quantité doit être un nombre entier.',
            'items.*.quantity.min' => 'La quantité doit être supérieure à zéro.',
            'disposition.required' => 'Choisissez la sortie du retour.',
            'disposition.in' => 'La sortie sélectionnée n’est pas valide.',
            'refund_method.required_if' => 'Choisissez le mode de remboursement.',
            'refund_method.in' => 'Le mode de remboursement n’est pas valide.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
