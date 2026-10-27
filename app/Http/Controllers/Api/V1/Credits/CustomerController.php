<?php

namespace App\Http\Controllers\Api\V1\Credits;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Resources\Api\V1\Credits\CustomerResource;
use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'active_only' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $activeStatuses = ['open', 'partial'];
        $today = now()->toDateString();

        $customers = Customer::query()
            ->select('customers.*')
            ->selectSub(
                CustomerCredit::query()
                    ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('status', $activeStatuses),
                'total_due',
            )
            ->selectSub(
                CustomerCredit::query()
                    ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0)')
                    ->whereColumn('customer_id', 'customers.id')
                    ->whereIn('status', $activeStatuses)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $today),
                'overdue_due',
            )
            ->withCount([
                'credits as open_credits_count' => fn ($query) => $query->whereIn('status', $activeStatuses),
            ])
            ->when((bool) ($validated['active_only'] ?? true), function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? 10))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'data' => CustomerResource::make($customer),
        ], 201);
    }
}
