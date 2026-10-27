<?php

namespace Tests\Feature;

use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class FinanceManagementApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It lists finance entries with filters and totals.
     */
    public function test_it_lists_finance_entries_with_filters(): void
    {
        FinancialEntry::query()->create([
            'entry_type' => 'income',
            'category' => 'Ventes comptoir',
            'amount' => 1400,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        FinancialEntry::query()->create([
            'entry_type' => 'expense',
            'category' => 'Loyer',
            'amount' => 500,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'bank',
        ]);

        FinancialEntry::query()->create([
            'entry_type' => 'expense',
            'category' => 'Transport',
            'amount' => 120,
            'occurred_on' => now()->subMonths(2)->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response = $this->getJson(route('api.v1.finance.entries.index', [
            'entry_type' => 'expense',
            'occurred_from' => now()->startOfMonth()->toDateString(),
            'occurred_to' => now()->endOfMonth()->toDateString(),
        ]));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', 1)
                ->where('data.0.entry_type', 'expense')
                ->where('data.0.category', 'Loyer')
                ->where('meta.filters.entry_type', 'expense')
                ->where('meta.totals.income_total', 0)
                ->where('meta.totals.expense_total', 500)
                ->where('meta.totals.net_result', -500)
                ->etc());
    }

    /**
     * It creates a finance entry.
     */
    public function test_it_creates_finance_entry(): void
    {
        $response = $this->postJson(route('api.v1.finance.entries.store'), [
            'entry_type' => 'income',
            'category' => 'Ventes livraison',
            'amount' => 980,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'mobile',
            'note' => 'Commande WhatsApp',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entry_type', 'income')
            ->assertJsonPath('data.category', 'Ventes livraison')
            ->assertJsonPath('data.amount', 980);

        $this->assertDatabaseHas('financial_entries', [
            'entry_type' => 'income',
            'category' => 'Ventes livraison',
            'payment_method' => 'mobile',
        ]);
    }

    /**
     * It creates a product sale paid cash and decrements stock.
     */
    public function test_it_creates_finance_sale_with_cash_and_updates_stock(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 20,
            'purchase_price' => 8,
            'selling_price' => 12.5,
        ]);

        $response = $this->postJson(route('api.v1.finance.sales.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => 'Vente comptoir',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entry.category', 'Vente produits')
            ->assertJsonPath('data.entry.payment_method', 'cash')
            ->assertJsonPath('data.entry.amount', 25)
            ->assertJsonPath('data.credit', null);

        $product->refresh();

        $this->assertSame(18.0, (float) $product->stock_quantity);

        $this->assertDatabaseHas('financial_entries', [
            'category' => 'Vente produits',
            'payment_method' => 'cash',
            'amount' => 25,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => 'out',
            'quantity' => 2,
        ]);
    }

    /**
     * It creates one sale with multiple products.
     */
    public function test_it_creates_multi_product_sale(): void
    {
        $productA = Product::factory()->create([
            'stock_quantity' => 30,
            'selling_price' => 10,
        ]);

        $productB = Product::factory()->create([
            'stock_quantity' => 12,
            'selling_price' => 7.5,
        ]);

        $response = $this->postJson(route('api.v1.finance.sales.store'), [
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2],
                ['product_id' => $productB->id, 'quantity' => 3],
            ],
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entry.payment_method', 'cash')
            ->assertJsonPath('data.entry.amount', 42.5)
            ->assertJsonCount(2, 'data.products');

        $productA->refresh();
        $productB->refresh();

        $this->assertSame(28.0, (float) $productA->stock_quantity);
        $this->assertSame(9.0, (float) $productB->stock_quantity);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'movement_type' => 'out',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'movement_type' => 'out',
            'quantity' => 3,
        ]);
    }

    /**
     * It appends credit sale amount to an existing customer ardoise.
     */
    public function test_it_appends_credit_sale_to_existing_customer_ardoise(): void
    {
        $customer = Customer::factory()->create();

        $existingCredit = CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 100,
            'paid_amount' => 20,
            'status' => 'partial',
        ]);

        $product = Product::factory()->create([
            'stock_quantity' => 15,
            'purchase_price' => 6,
            'selling_price' => 10,
        ]);

        $response = $this->postJson(route('api.v1.finance.sales.store'), [
            'product_id' => $product->id,
            'quantity' => 3,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'note' => 'Ajout ardoise',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entry.payment_method', 'credit')
            ->assertJsonPath('data.entry.customer_id', $customer->id)
            ->assertJsonPath('data.entry.amount', 30)
            ->assertJsonPath('data.credit.id', $existingCredit->id);

        $existingCredit->refresh();
        $product->refresh();

        $this->assertSame(130.0, (float) $existingCredit->total_amount);
        $this->assertSame(20.0, (float) $existingCredit->paid_amount);
        $this->assertSame('partial', $existingCredit->status);
        $this->assertSame(12.0, (float) $product->stock_quantity);
        $this->assertSame(1, CustomerCredit::query()->where('customer_id', $customer->id)->count());

        $this->assertDatabaseHas('financial_entries', [
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_credit_id' => $existingCredit->id,
            'amount' => 30,
        ]);
    }

    /**
     * It creates a new customer and ardoise for a credit sale.
     */
    public function test_it_creates_new_customer_for_credit_sale(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 9,
            'selling_price' => 15,
        ]);

        $response = $this->postJson(route('api.v1.finance.sales.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'credit',
            'new_customer' => [
                'name' => 'Client Nouveau',
                'phone' => '0600000000',
            ],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.entry.payment_method', 'credit')
            ->assertJsonPath('data.entry.amount', 30);

        $this->assertDatabaseHas('customers', [
            'name' => 'Client Nouveau',
            'phone' => '0600000000',
        ]);

        $customer = Customer::query()->where('name', 'Client Nouveau')->firstOrFail();
        $credit = CustomerCredit::query()->where('customer_id', $customer->id)->firstOrFail();

        $this->assertSame(30.0, (float) $credit->total_amount);
        $this->assertSame(0.0, (float) $credit->paid_amount);
        $this->assertSame('open', $credit->status);

        $this->assertDatabaseHas('financial_entries', [
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_credit_id' => $credit->id,
            'amount' => 30,
        ]);
    }

    /**
     * It creates a customer credit when payment method is credit.
     */
    public function test_it_creates_finance_credit_entry_for_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson(route('api.v1.finance.entries.store'), [
            'entry_type' => 'income',
            'category' => 'Vente a credit',
            'amount' => 240,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'note' => 'Client habituel',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.payment_method', 'credit')
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.customer.name', $customer->name);

        $entryId = (int) $response->json('data.id');

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entryId,
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
        ]);

        $entry = FinancialEntry::query()->findOrFail($entryId);

        $this->assertNotNull($entry->customer_credit_id);
        $this->assertDatabaseHas('customer_credits', [
            'id' => $entry->customer_credit_id,
            'customer_id' => $customer->id,
            'total_amount' => 240,
            'paid_amount' => 0,
            'status' => 'open',
        ]);
    }

    /**
     * It requires a customer when payment method is credit.
     */
    public function test_it_requires_customer_for_credit_payment_method(): void
    {
        $response = $this->postJson(route('api.v1.finance.entries.store'), [
            'entry_type' => 'income',
            'category' => 'Vente a credit',
            'amount' => 150,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'credit',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    /**
     * It updates a finance entry.
     */
    public function test_it_updates_finance_entry(): void
    {
        $entry = FinancialEntry::factory()->create([
            'entry_type' => 'expense',
            'category' => 'Transport',
            'amount' => 80,
            'payment_method' => 'cash',
        ]);

        $response = $this->patchJson(route('api.v1.finance.entries.update', $entry), [
            'entry_type' => 'expense',
            'category' => 'Electricite',
            'amount' => 160,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'bank',
            'note' => 'Facture mensuelle',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.category', 'Electricite')
            ->assertJsonPath('data.amount', 160);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entry->id,
            'category' => 'Electricite',
            'payment_method' => 'bank',
        ]);
    }

    /**
     * It deletes a finance entry.
     */
    public function test_it_deletes_finance_entry(): void
    {
        $entry = FinancialEntry::factory()->create();

        $response = $this->deleteJson(route('api.v1.finance.entries.destroy', $entry));

        $response->assertNoContent();

        $this->assertDatabaseMissing('financial_entries', [
            'id' => $entry->id,
        ]);
    }

    /**
     * It blocks credit unlink when related payments already exist.
     */
    public function test_it_blocks_credit_unlink_when_payments_exist(): void
    {
        $customer = Customer::factory()->create();

        $credit = CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 300,
            'paid_amount' => 120,
            'status' => 'partial',
        ]);

        CreditPayment::factory()->create([
            'customer_credit_id' => $credit->id,
            'amount' => 120,
        ]);

        $entry = FinancialEntry::factory()->create([
            'entry_type' => 'income',
            'category' => 'Vente a credit',
            'amount' => 300,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'credit',
            'customer_id' => $customer->id,
            'customer_credit_id' => $credit->id,
        ]);

        $response = $this->patchJson(route('api.v1.finance.entries.update', $entry), [
            'entry_type' => 'income',
            'category' => 'Vente reglee comptant',
            'amount' => 300,
            'occurred_on' => now()->toDateString(),
            'payment_method' => 'cash',
            'note' => null,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $entry->id,
            'payment_method' => 'credit',
            'customer_credit_id' => $credit->id,
        ]);
    }

    /**
     * It keeps realized gain unchanged for past sales when purchase price is updated later.
     */
    public function test_it_keeps_realized_gain_unchanged_when_purchase_price_changes_after_sale(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 20,
            'purchase_price' => 10,
            'selling_price' => 20,
        ]);

        $saleDate = now()->toDateString();

        $saleResponse = $this->postJson(route('api.v1.finance.sales.store'), [
            'product_id' => $product->id,
            'quantity' => 2,
            'occurred_on' => $saleDate,
            'payment_method' => 'cash',
        ]);

        $saleResponse
            ->assertCreated()
            ->assertJsonPath('data.entry.amount', 40)
            ->assertJsonPath('data.entry.payment_method', 'cash');

        $saleEntryId = (int) $saleResponse->json('data.entry.id');

        $beforeSummary = $this->getJson(route('api.v1.dashboard-summary'));

        $beforeSummary
            ->assertOk()
            ->assertJsonPath('data.finance.month_income', 40)
            ->assertJsonPath('data.finance.net_result', 40);

        $stockInResponse = $this->postJson(route('api.v1.stock.movements.store'), [
            'movement_type' => 'in',
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 14,
            'selling_price' => 25,
            'moved_at' => $saleDate,
        ]);

        $stockInResponse->assertCreated();

        $afterSummary = $this->getJson(route('api.v1.dashboard-summary'));

        $afterSummary
            ->assertOk()
            ->assertJsonPath('data.finance.month_income', 40)
            ->assertJsonPath('data.finance.net_result', 40);

        $this->assertDatabaseHas('financial_entries', [
            'id' => $saleEntryId,
            'amount' => 40,
            'payment_method' => 'cash',
        ]);
    }
}
