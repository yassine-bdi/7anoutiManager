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
        Schema::table('products', function (Blueprint $table): void {
            $table->float('purchase_price')->default(0)->change();
            $table->float('selling_price')->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->float('unit_cost')->nullable()->change();
        });

        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->float('amount')->change();
        });

        Schema::table('customer_credits', function (Blueprint $table): void {
            $table->float('total_amount')->change();
            $table->float('paid_amount')->default(0)->change();
        });

        Schema::table('credit_payments', function (Blueprint $table): void {
            $table->float('amount')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('purchase_price', 12, 2)->default(0)->change();
            $table->decimal('selling_price', 12, 2)->default(0)->change();
        });

        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->decimal('unit_cost', 12, 2)->nullable()->change();
        });

        Schema::table('financial_entries', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->change();
        });

        Schema::table('customer_credits', function (Blueprint $table): void {
            $table->decimal('total_amount', 12, 2)->change();
            $table->decimal('paid_amount', 12, 2)->default(0)->change();
        });

        Schema::table('credit_payments', function (Blueprint $table): void {
            $table->decimal('amount', 12, 2)->change();
        });
    }
};
