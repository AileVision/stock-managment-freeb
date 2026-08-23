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
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 100)->unique();
            $table->string('category', 100);
            $table->decimal('amount', 12, 2);
            $table->string('payment_method', 30)->default('cash');
            $table->date('expense_date');
            $table->string('description')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->index('expense_date');
            $table->index('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
