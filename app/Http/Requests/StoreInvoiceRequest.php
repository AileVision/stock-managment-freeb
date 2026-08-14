<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Models\Sale;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
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
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * The sale must be completed and not already invoiced.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sale = Sale::query()->whereKey((int) $this->input('sale_id'))->first();

            if ($sale === null) {
                return;
            }

            if ($sale->status !== Sale::STATUS_COMPLETED) {
                $validator->errors()->add('sale_id', 'Cette vente est annulée et ne peut pas être facturée.');
            }

            $alreadyInvoiced = Invoice::query()->where('sale_id', $sale->id)->exists();

            if ($alreadyInvoiced) {
                $validator->errors()->add('sale_id', 'Cette vente a déjà été facturée.');
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
            'sale_id.required' => 'Veuillez sélectionner une vente.',
            'sale_id.exists' => 'La vente sélectionnée n’existe pas.',
            'customer_name.required' => 'Le nom du client est obligatoire.',
            'customer_name.max' => 'Le nom du client ne doit pas dépasser 255 caractères.',
            'customer_email.email' => 'L’adresse e-mail n’est pas valide.',
            'customer_address.max' => 'L’adresse ne doit pas dépasser 500 caractères.',
            'notes.max' => 'La remarque ne doit pas dépasser 1000 caractères.',
        ];
    }
}
