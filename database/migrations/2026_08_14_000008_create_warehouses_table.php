<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // Stock par produit et par entrepôt. products.stock_quantity reste la
        // somme totale, maintenue automatiquement par le modèle ProductWarehouse.
        Schema::create('product_warehouse', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->unique(['product_id', 'warehouse_id']);
            $table->timestamps();
        });

        // Entrepôt par défaut : tout le stock existant y est rattaché.
        $defaultId = DB::table('warehouses')->insertGetId([
            'name' => 'Entrepôt principal',
            'code' => 'MAIN',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')
            ->select(['id', 'stock_quantity'])
            ->orderBy('id')
            ->eachById(function ($product) use ($defaultId): void {
                DB::table('product_warehouse')->insert([
                    'product_id' => $product->id,
                    'warehouse_id' => $defaultId,
                    'quantity' => max((int) $product->stock_quantity, 0),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }, 500);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_warehouse');
        Schema::dropIfExists('warehouses');
    }
};
