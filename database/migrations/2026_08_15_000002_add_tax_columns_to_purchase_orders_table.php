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
        // TVA applicable à la commande fournisseur. Les prix d'achat du
        // catalogue sont hors taxes : la TVA est ajoutée au taux de la commande.
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('tax_rates')
                ->nullOnDelete();
            $table->decimal('tax_percent', 5, 2)
                ->default(0)
                ->after('tax_rate_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_percent');
        });
    }
};
