<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SupplierRequest extends FormRequest
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
        $supplier = $this->route('supplier');
        $supplierId = $supplier instanceof Model ? $supplier->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('suppliers', 'name')->ignore($supplierId)],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
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
            'name.required' => 'Le nom du fournisseur est obligatoire.',
            'name.max' => 'Le nom du fournisseur ne doit pas dépasser 150 caractères.',
            'name.unique' => 'Un fournisseur portant ce nom existe déjà.',
            'email.email' => 'L’adresse e-mail n’est pas valide.',
            'email.max' => 'L’adresse e-mail ne doit pas dépasser 255 caractères.',
            'phone.max' => 'Le numéro de téléphone ne doit pas dépasser 50 caractères.',
            'address.max' => 'L’adresse ne doit pas dépasser 500 caractères.',
        ];
    }
}
