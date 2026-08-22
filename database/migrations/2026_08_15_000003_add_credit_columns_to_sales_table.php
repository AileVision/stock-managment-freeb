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
        Schema::table('sales', function (Blueprint $table) {
            // Client de la vente à crédit (ventes B2B / grossiste).
            $table->foreignId('customer_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();

            // Échéance de paiement pour les ventes à crédit.
            $table->date('credit_due_at')
                ->nullable()
                ->after('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('credit_due_at');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
