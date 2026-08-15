<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductWarehouse;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoInventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = collect([
            ['name' => 'Électronique', 'description' => 'Accessoires et appareils électroniques', 'color' => '#6366f1'],
            ['name' => 'Alimentation', 'description' => 'Produits alimentaires de base', 'color' => '#f59e0b'],
            ['name' => 'Boissons', 'description' => 'Eaux, sodas et boissons', 'color' => '#06b6d4'],
            ['name' => 'Hygiène', 'description' => 'Savons, soins et entretien', 'color' => '#10b981'],
            ['name' => 'Papeterie', 'description' => 'Fournitures de bureau et d’école', 'color' => '#8b5cf6'],
        ])->map(fn (array $data) => Category::create([
            ...$data,
            'slug' => Str::slug($data['name']),
        ]));

        $suppliers = collect([
            ['name' => 'Sodigros Distribution', 'email' => 'contact@sodigros.sn', 'phone' => '+221 33 800 11 22', 'address' => 'Dakar, Zone industrielle'],
            ['name' => 'ElectroTech Import', 'email' => 'ventes@electrotech.sn', 'phone' => '+221 77 555 44 33', 'address' => 'Dakar, Marché Castors'],
            ['name' => 'AgroFresh Sénégal', 'email' => 'info@agrofresh.sn', 'phone' => '+221 76 222 11 00', 'address' => 'Thiès, Route de Khombole'],
        ])->map(fn (array $data) => Supplier::create($data));

        $category = fn (string $name) => $categories->firstWhere('name', $name)?->id;
        $supplier = fn (string $name) => $suppliers->firstWhere('name', $name)?->id;

        $products = [
            ['name' => 'Riz parfumé 5kg', 'sku' => 'RIZ-001', 'unit' => 'kg', 'purchase_price' => 3200, 'sale_price' => 3800, 'stock_quantity' => 40, 'min_stock' => 10, 'category_id' => $category('Alimentation'), 'supplier_id' => $supplier('AgroFresh Sénégal')],
            ['name' => 'Huile végétale 1L', 'sku' => 'HUI-001', 'unit' => 'l', 'purchase_price' => 1200, 'sale_price' => 1500, 'stock_quantity' => 15, 'min_stock' => 8, 'category_id' => $category('Alimentation'), 'supplier_id' => $supplier('AgroFresh Sénégal')],
            ['name' => 'Sucre en poudre 1kg', 'sku' => 'SUC-001', 'unit' => 'kg', 'purchase_price' => 650, 'sale_price' => 800, 'stock_quantity' => 25, 'min_stock' => 10, 'category_id' => $category('Alimentation'), 'supplier_id' => $supplier('Sodigros Distribution')],
            ['name' => 'Lait concentré sucré', 'sku' => 'LAI-001', 'unit' => 'box', 'purchase_price' => 900, 'sale_price' => 1100, 'stock_quantity' => 0, 'min_stock' => 20, 'category_id' => $category('Alimentation'), 'supplier_id' => $supplier('Sodigros Distribution')],
            ['name' => 'Café soluble 200g', 'sku' => 'CAF-001', 'unit' => 'pcs', 'purchase_price' => 1800, 'sale_price' => 2300, 'stock_quantity' => 18, 'min_stock' => 6, 'category_id' => $category('Alimentation'), 'supplier_id' => $supplier('AgroFresh Sénégal')],
            ['name' => 'Eau minérale 1,5L (pack de 6)', 'sku' => 'EAU-001', 'unit' => 'pack', 'purchase_price' => 2400, 'sale_price' => 3000, 'stock_quantity' => 30, 'min_stock' => 12, 'category_id' => $category('Boissons'), 'supplier_id' => $supplier('Sodigros Distribution')],
            ['name' => 'Savon de Marseille 250g', 'sku' => 'SAV-001', 'unit' => 'pcs', 'purchase_price' => 500, 'sale_price' => 750, 'stock_quantity' => 5, 'min_stock' => 12, 'category_id' => $category('Hygiène'), 'supplier_id' => $supplier('Sodigros Distribution')],
            ['name' => 'Détergent liquide 2L', 'sku' => 'DET-001', 'unit' => 'l', 'purchase_price' => 1500, 'sale_price' => 1900, 'stock_quantity' => 14, 'min_stock' => 6, 'category_id' => $category('Hygiène'), 'supplier_id' => $supplier('Sodigros Distribution')],
            ['name' => 'Câble HDMI 2m', 'sku' => 'CAB-001', 'unit' => 'pcs', 'purchase_price' => 2000, 'sale_price' => 3500, 'stock_quantity' => 12, 'min_stock' => 3, 'category_id' => $category('Électronique'), 'supplier_id' => $supplier('ElectroTech Import')],
            ['name' => 'Chargeur USB-C 20W', 'sku' => 'CHA-001', 'unit' => 'pcs', 'purchase_price' => 4000, 'sale_price' => 5500, 'stock_quantity' => 2, 'min_stock' => 5, 'category_id' => $category('Électronique'), 'supplier_id' => $supplier('ElectroTech Import')],
            ['name' => 'Stylo à bille (paquet de 10)', 'sku' => 'STY-001', 'unit' => 'pack', 'purchase_price' => 1500, 'sale_price' => 2000, 'stock_quantity' => 45, 'min_stock' => 15, 'category_id' => $category('Papeterie'), 'supplier_id' => $supplier('ElectroTech Import')],
        ];

        // Rattache le stock de démo à l'entrepôt par défaut (le seeder tourne
        // avec WithoutModelEvents, donc le hook de création de Product est
        // silencieux et le rattachement doit être fait explicitement).
        $defaultWarehouseId = Warehouse::defaultId();

        foreach ($products as $product) {
            $product = Product::create($product);

            if ($defaultWarehouseId !== null && $product->stock_quantity > 0) {
                ProductWarehouse::create([
                    'product_id' => $product->id,
                    'warehouse_id' => $defaultWarehouseId,
                    'quantity' => $product->stock_quantity,
                ]);
            }
        }

        $admin = User::where('email', 'admin@example.com')->first();

        $movements = [
            ['product' => 'RIZ-001', 'type' => 'in', 'quantity' => 50, 'reason' => 'Réception commande fournisseur', 'reference' => 'BL-2026-001'],
            ['product' => 'RIZ-001', 'type' => 'out', 'quantity' => -10, 'reason' => 'Vente au comptoir'],
            ['product' => 'HUI-001', 'type' => 'in', 'quantity' => 20, 'reason' => 'Réception commande fournisseur', 'reference' => 'BL-2026-002'],
            ['product' => 'SUC-001', 'type' => 'in', 'quantity' => 30, 'reason' => 'Réception commande fournisseur', 'reference' => 'BL-2026-003'],
            ['product' => 'SUC-001', 'type' => 'out', 'quantity' => -5, 'reason' => 'Vente en gros'],
            ['product' => 'LAI-001', 'type' => 'out', 'quantity' => -22, 'reason' => 'Ventes sur le mois'],
            ['product' => 'SAV-001', 'type' => 'adjustment', 'quantity' => 2, 'reason' => 'Inventaire physique : écart constaté'],
            ['product' => 'CHA-001', 'type' => 'adjustment', 'quantity' => -1, 'reason' => 'Produit défectueux retourné'],
            ['product' => 'EAU-001', 'type' => 'in', 'quantity' => 40, 'reason' => 'Réception commande fournisseur', 'reference' => 'BL-2026-004'],
        ];

        foreach ($movements as $movement) {
            $product = Product::where('sku', $movement['product'])->firstOrFail();

            $product->stockMovements()->create([
                'type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'reason' => $movement['reason'],
                'reference' => $movement['reference'] ?? null,
                'user_id' => $admin?->id,
            ]);
        }
    }
}
