<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display all categories with their product count.
     */
    public function index(): Response
    {
        return Inertia::render('stock/categories', [
            'categories' => Category::withCount('products')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create([
            ...$request->validated(),
            'slug' => Str::slug((string) $request->validated('name')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catégorie créée avec succès.']);

        return back();
    }

    /**
     * Update the specified category.
     */
    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update([
            ...$request->validated(),
            'slug' => Str::slug((string) $request->validated('name')),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catégorie mise à jour avec succès.']);

        return back();
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catégorie supprimée avec succès.']);

        return back();
    }
}
