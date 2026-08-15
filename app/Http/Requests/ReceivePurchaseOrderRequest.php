<?php

namespace App\Http\Requests;

use App\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseOrderRequest extends FormRequest
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
            'items.*.id' => ['required', 'integer'],
            'items.*.received_quantity' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }

    /**
     * Each received quantity must stay within the ordered quantity.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $order = $this->route('purchaseOrder');

            if (! $order instanceof PurchaseOrder) {
                return;
            }

            foreach ($this->input('items', []) as $index => $item) {
                $itemId = $item['id'] ?? null;
                $line = $itemId !== null
                    ? $order->items()->whereKey((int) $itemId)->first()
                    : null;

                if ($line === null) {
                    $validator->errors()->add("items.{$index}.id", 'Ligne de commande inconnue.');

                    continue;
                }

                $max = $line->quantity - $line->received_quantity;

                if ((int) $item['received_quantity'] > $max) {
                    $validator->errors()->add(
                        "items.{$index}.received_quantity",
                        "La quantité reçue dépasse le restant à recevoir ({$max}).",
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
            'items.required' => 'Aucune ligne à réceptionner.',
            'items.min' => 'Aucune ligne à réceptionner.',
            'items.*.received_quantity.required' => 'La quantité reçue est obligatoire pour chaque ligne.',
            'items.*.received_quantity.integer' => 'La quantité reçue doit être un nombre entier.',
            'items.*.received_quantity.min' => 'La quantité reçue doit être positive ou nulle.',
            'items.*.received_quantity.max' => 'La quantité reçue est trop élevée.',
        ];
    }
}
