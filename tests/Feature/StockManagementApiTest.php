<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class StockManagementApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * It lists products with stock metadata.
     */
    public function test_it_lists_stock_products(): void
    {
        $category = ProductCategory::factory()->create(['name' => 'Boissons']);

        Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Eau 1.5L',
            'stock_quantity' => 3,
            'low_stock_threshold' => 5,
        ]);

        $response = $this->getJson(route('api.v1.stock.products.index'));

        $response
            ->assertOk()
            ->assertJson(fn (AssertableJson $json): AssertableJson => $json
                ->has('data', 1)
                ->where('data.0.name', 'Eau 1.5L')
                ->where('data.0.is_low_stock', true)
                ->where('data.0.image_url', null)
                ->where('data.0.display_image_url', asset('images/product-default.svg'))
                ->has('meta.categories', 1)
                ->etc());
    }

    /**
     * It filters stock products by search term.
     */
    public function test_it_filters_stock_products_by_search_term(): void
    {
        $beveragesCategory = ProductCategory::factory()->create(['name' => 'Boissons']);
        $groceryCategory = ProductCategory::factory()->create(['name' => 'Epicerie']);

        Product::factory()->create([
            'product_category_id' => $beveragesCategory->id,
            'name' => 'Jus orange',
        ]);

        Product::factory()->create([
            'product_category_id' => $groceryCategory->id,
            'name' => 'Farine blanche',
        ]);

        $responseByName = $this->getJson(route('api.v1.stock.products.index', [
            'search' => 'orange',
        ]));

        $responseByName
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Jus orange');

        $responseByCategory = $this->getJson(route('api.v1.stock.products.index', [
            'search' => 'epicerie',
        ]));

        $responseByCategory
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Farine blanche');
    }

    /**
     * It uses default image when stored product image is missing.
     */
    public function test_it_uses_default_image_when_product_file_is_missing(): void
    {
        Product::factory()->create([
            'name' => 'Produit sans fichier',
            'image_path' => 'products/missing-file.jpg',
        ]);

        $response = $this->getJson(route('api.v1.stock.products.index'));

        $response
            ->assertOk()
            ->assertJsonPath('data.0.image_url', null)
            ->assertJsonPath('data.0.display_image_url', asset('images/product-default.svg'));
    }

    /**
     * It creates a stock product.
     */
    public function test_it_creates_stock_product(): void
    {
        $category = ProductCategory::factory()->create();

        $response = $this->postJson(route('api.v1.stock.products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Lait entier',
            'unit' => 'piece',
            'stock_quantity' => 12,
            'low_stock_threshold' => 4,
            'purchase_price' => 7.5,
            'selling_price' => 9,
            'is_active' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Lait entier');

        $this->assertDatabaseHas('products', [
            'name' => 'Lait entier',
        ]);
    }

    /**
     * It creates a stock product with an image.
     */
    public function test_it_creates_stock_product_with_image(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create();

        $response = $this->post(route('api.v1.stock.products.store'), [
            'product_category_id' => $category->id,
            'name' => 'Huile olive',
            'unit' => 'piece',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'purchase_price' => 30,
            'selling_price' => 40,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('huile.jpg', 640, 640),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated();

        $product = Product::query()->where('name', 'Huile olive')->firstOrFail();

        $this->assertNotNull($product->image_path);
        $this->assertTrue(Storage::disk('public')->exists((string) $product->image_path));
        $response->assertJsonPath('data.display_image_url', asset('storage/'.(string) $product->image_path));
    }

    /**
     * It applies a stock out movement and updates product quantity.
     */
    public function test_it_registers_stock_movement_and_updates_quantity(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
        ]);

        $response = $this->postJson(route('api.v1.stock.movements.store'), [
            'product_id' => $product->id,
            'movement_type' => 'out',
            'quantity' => 3,
            'note' => 'Vente caisse',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.product.stock_quantity', 7);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'movement_type' => 'out',
        ]);

        $this->assertEquals('7.000', Product::query()->findOrFail($product->id)->stock_quantity);
    }

    /**
     * It rejects stock out movement when quantity exceeds stock.
     */
    public function test_it_rejects_out_movement_when_stock_is_insufficient(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 2,
        ]);

        $response = $this->postJson(route('api.v1.stock.movements.store'), [
            'product_id' => $product->id,
            'movement_type' => 'out',
            'quantity' => 9,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);

        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertEquals('2.000', Product::query()->findOrFail($product->id)->stock_quantity);
    }

    /**
     * It adds stock for multiple products and updates purchase price when provided.
     */
    public function test_it_adds_stock_for_multiple_products_with_optional_unit_cost_update(): void
    {
        $productA = Product::factory()->create([
            'stock_quantity' => 5,
            'purchase_price' => 10,
            'selling_price' => 14,
        ]);

        $productB = Product::factory()->create([
            'stock_quantity' => 2,
            'purchase_price' => 7,
            'selling_price' => 9,
        ]);

        $response = $this->postJson(route('api.v1.stock.movements.store'), [
            'movement_type' => 'in',
            'items' => [
                [
                    'product_id' => $productA->id,
                    'quantity' => 3,
                    'unit_cost' => 11.5,
                    'selling_price' => 16.5,
                ],
                [
                    'product_id' => $productB->id,
                    'quantity' => 4,
                ],
            ],
            'note' => 'Reception fournisseur',
        ]);

        $response
            ->assertCreated()
            ->assertJsonCount(2, 'data.products')
            ->assertJsonCount(2, 'data.movements');

        $productA->refresh();
        $productB->refresh();

        $this->assertSame(8.0, (float) $productA->stock_quantity);
        $this->assertSame(6.0, (float) $productB->stock_quantity);
        $this->assertSame(11.5, (float) $productA->purchase_price);
        $this->assertSame(7.0, (float) $productB->purchase_price);
        $this->assertSame(16.5, (float) $productA->selling_price);
        $this->assertSame(9.0, (float) $productB->selling_price);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'movement_type' => 'in',
            'quantity' => 3,
            'unit_cost' => 11.5,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'movement_type' => 'in',
            'quantity' => 4,
            'unit_cost' => 7,
        ]);
    }

    /**
     * It keeps purchase price unchanged when unit cost is not provided.
     */
    public function test_it_keeps_existing_purchase_price_when_unit_cost_is_empty(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 10,
            'purchase_price' => 13.75,
        ]);

        $response = $this->postJson(route('api.v1.stock.movements.store'), [
            'movement_type' => 'in',
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $response->assertCreated();

        $product->refresh();

        $this->assertSame(12.0, (float) $product->stock_quantity);
        $this->assertSame(13.75, (float) $product->purchase_price);

        $movement = StockMovement::query()->latest('id')->firstOrFail();
        $this->assertSame(13.75, (float) $movement->unit_cost);
    }

    /**
     * It requires selling price when purchase price is changed on stock in.
     */
    public function test_it_requires_selling_price_when_purchase_price_changes_on_stock_in(): void
    {
        $product = Product::factory()->create([
            'stock_quantity' => 4,
            'purchase_price' => 9,
            'selling_price' => 12,
        ]);

        $response = $this->postJson(route('api.v1.stock.movements.store'), [
            'movement_type' => 'in',
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_cost' => 10.5,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['selling_price']);

        $product->refresh();
        $this->assertSame(4.0, (float) $product->stock_quantity);
        $this->assertSame(9.0, (float) $product->purchase_price);
        $this->assertSame(12.0, (float) $product->selling_price);
    }

    /**
     * It updates a product.
     */
    public function test_it_updates_a_stock_product(): void
    {
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'name' => 'Produit initial',
            'unit' => 'piece',
            'stock_quantity' => 8,
            'low_stock_threshold' => 2,
            'purchase_price' => 5,
            'selling_price' => 7,
            'is_active' => true,
        ]);

        $response = $this->patchJson(route('api.v1.stock.products.update', $product), [
            'product_category_id' => $category->id,
            'name' => 'Produit modifie',
            'unit' => 'piece',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'purchase_price' => 6,
            'selling_price' => 8.5,
            'is_active' => true,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Produit modifie')
            ->assertJsonPath('data.selling_price', 8.5);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Produit modifie',
            'selling_price' => 8.5,
        ]);
    }

    /**
     * It updates product image and removes previous file.
     */
    public function test_it_replaces_product_image_and_cleans_previous_file(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create();
        $oldImagePath = UploadedFile::fake()->image('old.jpg', 640, 640)->store('products', 'public');

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'image_path' => $oldImagePath,
            'name' => 'Produit image',
            'unit' => 'piece',
            'stock_quantity' => 10,
            'low_stock_threshold' => 3,
            'purchase_price' => 5,
            'selling_price' => 8,
            'is_active' => true,
        ]);

        $response = $this->post(route('api.v1.stock.products.update', $product), [
            '_method' => 'PATCH',
            'product_category_id' => $category->id,
            'name' => 'Produit image maj',
            'unit' => 'piece',
            'stock_quantity' => 11,
            'low_stock_threshold' => 3,
            'purchase_price' => 6,
            'selling_price' => 9,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new.jpg', 640, 640),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $product->refresh();

        $this->assertNotNull($product->image_path);
        $this->assertNotSame($oldImagePath, $product->image_path);
        $this->assertFalse(Storage::disk('public')->exists($oldImagePath));
        $this->assertTrue(Storage::disk('public')->exists((string) $product->image_path));
    }

    /**
     * It removes product image when requested.
     */
    public function test_it_can_remove_product_image(): void
    {
        Storage::fake('public');
        $category = ProductCategory::factory()->create();
        $imagePath = UploadedFile::fake()->image('to-remove.jpg', 640, 640)->store('products', 'public');

        $product = Product::factory()->create([
            'product_category_id' => $category->id,
            'image_path' => $imagePath,
            'name' => 'Produit suppression image',
            'unit' => 'piece',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'purchase_price' => 4,
            'selling_price' => 6,
            'is_active' => true,
        ]);

        $response = $this->post(route('api.v1.stock.products.update', $product), [
            '_method' => 'PATCH',
            'product_category_id' => $category->id,
            'name' => 'Produit suppression image',
            'unit' => 'piece',
            'stock_quantity' => 5,
            'low_stock_threshold' => 2,
            'purchase_price' => 4,
            'selling_price' => 6,
            'is_active' => true,
            'remove_image' => true,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk();

        $product->refresh();
        $this->assertNull($product->image_path);
        $this->assertFalse(Storage::disk('public')->exists($imagePath));
    }

    /**
     * It deletes a product.
     */
    public function test_it_deletes_a_stock_product(): void
    {
        Storage::fake('public');
        $imagePath = UploadedFile::fake()->image('delete-me.jpg', 640, 640)->store('products', 'public');

        $product = Product::factory()->create([
            'image_path' => $imagePath,
        ]);

        $response = $this->deleteJson(route('api.v1.stock.products.destroy', $product));

        $response->assertNoContent();

        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);

        $this->assertFalse(Storage::disk('public')->exists($imagePath));
    }
}
