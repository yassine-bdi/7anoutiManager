<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Resources\Api\V1\Stock\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));

        $productsQuery = Product::query()
            ->with('category:id,name');

        if ($search !== '') {
            $productsQuery->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhereHas('category', function ($categoryQuery) use ($search): void {
                        $categoryQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $products = $productsQuery
            ->orderByRaw('stock_quantity <= low_stock_threshold DESC')
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        $categories = ProductCategory::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
            ])
            ->values()
            ->all();

        return ProductResource::collection($products)
            ->additional([
                'meta' => [
                    'categories' => $categories,
                ],
            ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $imagePath = $this->storeProductImage($request->file('image'));

        $product = Product::query()->create([
            ...$this->extractPersistableAttributes($validated),
            'image_path' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'data' => ProductResource::make($product->load('category:id,name')),
        ], 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreProductRequest $request, Product $product): JsonResponse
    {
        $validated = $request->validated();
        $currentImagePath = $product->image_path;
        $uploadedImagePath = $this->storeProductImage($request->file('image'));

        $nextImagePath = $currentImagePath;

        if ($request->boolean('remove_image')) {
            $nextImagePath = null;
        }

        if (is_string($uploadedImagePath)) {
            $nextImagePath = $uploadedImagePath;
        }

        $product->update([
            ...$this->extractPersistableAttributes($validated),
            'image_path' => $nextImagePath,
            'is_active' => $request->boolean('is_active', true),
        ]);

        if (
            is_string($currentImagePath)
            && $currentImagePath !== ''
            && $currentImagePath !== $nextImagePath
        ) {
            Storage::disk('public')->delete($currentImagePath);
        }

        return response()->json([
            'data' => ProductResource::make($product->refresh()->load('category:id,name')),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product): JsonResponse
    {
        $productImagePath = $product->image_path;
        $product->delete();

        if (is_string($productImagePath) && $productImagePath !== '') {
            Storage::disk('public')->delete($productImagePath);
        }

        return response()->json([], 204);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function extractPersistableAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['image', 'remove_image'])
            ->all();
    }

    private function storeProductImage(mixed $file): ?string
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        return $file->store('products', 'public');
    }
}
