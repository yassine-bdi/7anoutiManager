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
        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->enum('entry_type', ['income', 'expense']);
            $table->string('category');
            $table->decimal('amount', 12, 2);
            $table->date('occurred_on');
            $table->string('payment_method')->default('cash');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['entry_type', 'occurred_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
