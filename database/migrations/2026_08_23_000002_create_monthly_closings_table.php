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
        Schema::create('monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('closed');

            // Recette (ventes)
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('revenue_ht', 14, 2)->default(0);

            // Achats (réceptions fournisseur)
            $table->decimal('purchases', 14, 2)->default(0);
            $table->decimal('purchases_ht', 14, 2)->default(0);
            $table->unsignedInteger('purchases_count')->default(0);

            // Dépenses d'exploitation
            $table->decimal('expenses', 14, 2)->default(0);
            $table->unsignedInteger('expenses_count')->default(0);

            // TVA
            $table->decimal('vat_collected', 14, 2)->default(0);
            $table->decimal('vat_deductible', 14, 2)->default(0);
            $table->decimal('vat_net', 14, 2)->default(0);

            // Résultat
            $table->decimal('gross_margin', 14, 2)->default(0);
            $table->decimal('net_result', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->timestamp('closed_at');
            $table->timestamps();

            $table->unique(['year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_closings');
    }
};
