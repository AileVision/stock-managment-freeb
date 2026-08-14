<?php

namespace App\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
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
        $category = $this->route('category');
        $categoryId = $category instanceof Model ? $category->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($categoryId)],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
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
            'name.required' => 'Le nom de la catégorie est obligatoire.',
            'name.max' => 'Le nom de la catégorie ne doit pas dépasser 100 caractères.',
            'name.unique' => 'Une catégorie portant ce nom existe déjà.',
            'description.max' => 'La description ne doit pas dépasser 500 caractères.',
            'color.max' => 'La couleur est invalide.',
        ];
    }
}
