<?php

namespace Tests\Feature;

use App\Models\CreditPayment;
use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class CreditsManagementApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It lists customers with due balances.
     */
    public function test_it_lists_credit_customers_with_balances(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Client Hanout Nord',
        ]);

        CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 500,
            'paid_amount' => 100,
            'status' => 'partial',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $response = $this->getJson(route('api.v1.credits.customers.index'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', 1)
                ->where('data.0.name', 'Client Hanout Nord')
                ->where('data.0.open_credits_count', 1)
                ->where('data.0.total_due', 400)
                ->where('data.0.overdue_due', 400)
                ->etc());
    }

    /**
     * It creates a customer.
     */
    public function test_it_creates_a_customer(): void
    {
        $response = $this->postJson(route('api.v1.credits.customers.store'), [
            'name' => 'Client Test',
            'phone' => '0600000000',
            'address' => 'Quartier centre',
            'notes' => 'Achete souvent a credit',
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Client Test');

        $this->assertDatabaseHas('customers', [
            'name' => 'Client Test',
            'phone' => '0600000000',
        ]);
    }

    /**
     * It creates a credit entry.
     */
    public function test_it_creates_credit_entry(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->postJson(route('api.v1.credits.entries.store'), [
            'customer_id' => $customer->id,
            'total_amount' => 350,
            'opened_on' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'note' => 'Ardoise debut septembre',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.customer_id', $customer->id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.remaining_amount', 350);

        $this->assertDatabaseHas('customer_credits', [
            'customer_id' => $customer->id,
            'status' => 'open',
        ]);
    }

    /**
     * It lists credit entries with overdue filter and totals.
     */
    public function test_it_lists_credit_entries_with_filters_and_totals(): void
    {
        $customer = Customer::factory()->create();

        CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 600,
            'paid_amount' => 200,
            'status' => 'partial',
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 300,
            'paid_amount' => 0,
            'status' => 'open',
            'due_date' => now()->addDays(4)->toDateString(),
        ]);

        $response = $this->getJson(route('api.v1.credits.entries.index', [
            'overdue_only' => true,
        ]));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', 1)
                ->where('meta.totals.total_due', 400)
                ->where('meta.totals.overdue_amount', 400)
                ->etc());
    }

    /**
     * It registers a payment and updates credit status.
     */
    public function test_it_registers_payment_and_updates_credit(): void
    {
        $credit = CustomerCredit::factory()->create([
            'total_amount' => 300,
            'paid_amount' => 0,
            'status' => 'open',
        ]);

        $response = $this->postJson(route('api.v1.credits.entries.payments.store', $credit), [
            'amount' => 120,
            'paid_at' => now()->toIso8601String(),
            'note' => 'Paiement partiel',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.credit.paid_amount', 120)
            ->assertJsonPath('data.credit.status', 'partial');

        $this->assertDatabaseHas('credit_payments', [
            'customer_credit_id' => $credit->id,
            'amount' => 120,
        ]);

        $this->assertDatabaseHas('financial_entries', [
            'entry_type' => 'income',
            'category' => 'Reglement credit client',
            'payment_method' => 'cash',
            'customer_credit_id' => $credit->id,
            'amount' => 120,
        ]);
    }

    /**
     * It rejects payment amount higher than remaining due.
     */
    public function test_it_rejects_overpayment(): void
    {
        $credit = CustomerCredit::factory()->create([
            'total_amount' => 200,
            'paid_amount' => 150,
            'status' => 'partial',
        ]);

        $response = $this->postJson(route('api.v1.credits.entries.payments.store', $credit), [
            'amount' => 80,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount']);

        $this->assertDatabaseCount('credit_payments', 0);
        $this->assertEquals('150.00', CustomerCredit::query()->findOrFail($credit->id)->paid_amount);

        $this->assertEquals(0, CreditPayment::query()->count());
    }

    /**
     * It updates a credit entry.
     */
    public function test_it_updates_credit_entry(): void
    {
        $customer = Customer::factory()->create();

        $credit = CustomerCredit::factory()->create([
            'customer_id' => $customer->id,
            'total_amount' => 300,
            'paid_amount' => 100,
            'status' => 'partial',
            'opened_on' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->patchJson(route('api.v1.credits.entries.update', $credit), [
            'customer_id' => $customer->id,
            'total_amount' => 420,
            'opened_on' => now()->toDateString(),
            'due_date' => now()->addDays(8)->toDateString(),
            'note' => 'Ardoise ajustee',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.total_amount', 420)
            ->assertJsonPath('data.status', 'partial')
            ->assertJsonPath('data.note', 'Ardoise ajustee');

        $this->assertDatabaseHas('customer_credits', [
            'id' => $credit->id,
            'total_amount' => 420,
            'paid_amount' => 100,
            'status' => 'partial',
        ]);
    }

    /**
     * It deletes a credit entry.
     */
    public function test_it_deletes_credit_entry(): void
    {
        $credit = CustomerCredit::factory()->create();

        $response = $this->deleteJson(route('api.v1.credits.entries.destroy', $credit));

        $response->assertNoContent();

        $this->assertDatabaseMissing('customer_credits', [
            'id' => $credit->id,
        ]);
    }
}
