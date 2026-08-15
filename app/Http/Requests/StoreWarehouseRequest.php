<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
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
        $warehouse = $this->route('warehouse');
        $warehouseId = $warehouse instanceof Model ? $warehouse->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:20', Rule::unique('warehouses', 'code')->ignore($warehouseId)],
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
            'name.required' => 'Le nom de l’entrepôt est obligatoire.',
            'name.max' => 'Le nom de l’entrepôt ne doit pas dépasser 150 caractères.',
            'code.required' => 'Le code de l’entrepôt est obligatoire.',
            'code.unique' => 'Ce code d’entrepôt est déjà utilisé.',
            'code.max' => 'Le code ne doit pas dépasser 20 caractères.',
            'address.max' => 'L’adresse ne doit pas dépasser 500 caractères.',
        ];
    }
}
