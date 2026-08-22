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
        // Retours clients : annulation totale ou partielle d'une vente.
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            // Valeur des marchandises retournées (aux prix de la vente d'origine).
            $table->decimal('total', 12, 2)->default(0);
            // Sort du retour : cash / card / transfer / tmoney / flooz pour un
            // remboursement, « avoir » pour un avoir, « none » si rien à rembourser
            // (le retour réduit uniquement le solde impayé d'une vente à crédit).
            $table->string('refund_method')->nullable();
            // Montant effectivement remboursé ou converti en avoir.
            $table->decimal('refund_amount', 12, 2)->default(0);
            // Part du retour appliquée au solde impayé de la vente à crédit.
            $table->decimal('applied_to_balance', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->date('returned_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity');
            // Prix unitaire figé au moment de la vente d'origine.
            $table->decimal('unit_price', 12, 2);
            $table->timestamps();
        });

        // Avoirs : crédit accordé au client, utilisable sur les ventes futures.
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('sale_return_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            // Part de l'avoir déjà utilisée lors de ventes suivantes.
            $table->decimal('used_amount', 12, 2)->default(0);
            $table->string('status')->default('issued');
            $table->date('issued_at')->useCurrent();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
