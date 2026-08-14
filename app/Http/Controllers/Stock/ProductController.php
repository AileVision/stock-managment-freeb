<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a paginated, filterable list of products.
     */
    public function index(Request $request): Response
    {
        $products = Product::query()
            ->with(['category', 'supplier'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->string('search'));

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->boolean('low_stock'), function ($query) {
                $query->where('min_stock', '>', 0)->whereColumn('stock_quantity', '<=', 'min_stock');
            })
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('stock/products', [
            'products' => $products,
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'category_id' => $request->input('category_id'),
                'supplier_id' => $request->input('supplier_id'),
                'low_stock' => $request->boolean('low_stock'),
            ],
        ]);
    }

    /**
     * Store a newly created product, optionally with an opening stock movement.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $initialQuantity = (int) ($data['stock_quantity'] ?? 0);
        unset($data['stock_quantity']);

        DB::transaction(function () use ($data, $initialQuantity): void {
            $product = Product::create($data);

            if ($initialQuantity > 0) {
                $product->increment('stock_quantity', $initialQuantity);
                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $initialQuantity,
                    'reason' => 'Stock initial à la création du produit',
                    'user_id' => auth()->id(),
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produit créé avec succès.']);

        return to_route('stock.products.index');
    }

    /**
     * Update the specified product (stock quantity is changed via movements only).
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        unset($data['stock_quantity']);

        $product->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produit mis à jour avec succès.']);

        return to_route('stock.products.index');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Produit supprimé avec succès.']);

        return to_route('stock.products.index');
    }
}
