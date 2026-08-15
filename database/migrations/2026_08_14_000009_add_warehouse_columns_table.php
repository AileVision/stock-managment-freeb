<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('user_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            // Entrepôt de réception cible de la commande.
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('user_id')
                ->constrained('warehouses')
                ->nullOnDelete();
        });

        // Quantité réellement reçue : permet les réceptions partielles.
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->integer('received_quantity')->default(0)->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }
};
