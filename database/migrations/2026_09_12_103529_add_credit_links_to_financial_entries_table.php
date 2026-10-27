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
        Schema::table('financial_entries', function (Blueprint $table) {
            $table->foreignId('customer_id')
                ->nullable()
                ->after('payment_method')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('customer_credit_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_credits')
                ->nullOnDelete();

            $table->index(['payment_method', 'customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_entries', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'customer_id']);
            $table->dropConstrainedForeignId('customer_credit_id');
            $table->dropConstrainedForeignId('customer_id');
        });
    }
};
