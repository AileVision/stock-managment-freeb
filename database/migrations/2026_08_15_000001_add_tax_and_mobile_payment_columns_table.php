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
        // TVA appliquée aux ventes (taux inclus : extraite du total).
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('tax_rates')
                ->nullOnDelete();
            $table->decimal('tax_percent', 5, 2)
                ->default(0)
                ->after('tax_rate_id');

            // Paiement mobile (Togo) : T-Money / Flooz.
            $table->string('mobile_phone')
                ->nullable()
                ->after('tax_percent');
            $table->string('mobile_reference')
                ->nullable()
                ->after('mobile_phone');
        });

        Schema::table('quotes', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')
                ->nullable()
                ->after('discount_percent')
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
        Schema::table('quotes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
            $table->dropColumn('tax_percent');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['mobile_reference', 'mobile_phone', 'tax_percent']);
            $table->dropConstrainedForeignId('tax_rate_id');
        });
    }
};
