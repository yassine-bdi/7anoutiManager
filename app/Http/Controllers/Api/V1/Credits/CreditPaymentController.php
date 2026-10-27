<?php

namespace App\Http\Controllers\Api\V1\Credits;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCreditPaymentRequest;
use App\Http\Resources\Api\V1\Credits\CreditPaymentResource;
use App\Http\Resources\Api\V1\Credits\CustomerCreditResource;
use App\Models\CreditPayment;
use App\Models\CustomerCredit;
use App\Models\FinancialEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditPaymentController extends Controller
{
    /**
     * Store a new payment and apply it to the customer credit.
     */
    public function store(StoreCreditPaymentRequest $request, CustomerCredit $customerCredit): JsonResponse
    {
        $validated = $request->validated();

        [$credit, $payment] = DB::transaction(function () use ($customerCredit, $validated): array {
            $credit = CustomerCredit::query()
                ->lockForUpdate()
                ->findOrFail($customerCredit->id);

            $paymentAmount = (float) $validated['amount'];
            $remainingAmount = (float) $credit->total_amount - (float) $credit->paid_amount;

            if ($paymentAmount > $remainingAmount + 0.0001) {
                throw ValidationException::withMessages([
                    'amount' => 'Le montant depasse le reste a payer.',
                ]);
            }

            $newPaidAmount = (float) $credit->paid_amount + $paymentAmount;
            $totalAmount = (float) $credit->total_amount;

            $newStatus = 'open';

            if ($newPaidAmount > 0 && $newPaidAmount < $totalAmount) {
                $newStatus = 'partial';
            }

            if (abs($newPaidAmount - $totalAmount) < 0.0001) {
                $newStatus = 'paid';
            }

            $credit->update([
                'paid_amount' => round($newPaidAmount, 2),
                'status' => $newStatus,
            ]);

            $payment = CreditPayment::query()->create([
                'customer_credit_id' => $credit->id,
                'amount' => $paymentAmount,
                'paid_at' => $validated['paid_at'] ?? now(),
                'note' => $validated['note'] ?? null,
            ]);

            FinancialEntry::query()->create([
                'entry_type' => 'income',
                'category' => 'Reglement credit client',
                'amount' => $paymentAmount,
                'occurred_on' => $payment->paid_at?->toDateString() ?? now()->toDateString(),
                'payment_method' => (string) ($validated['payment_method'] ?? 'cash'),
                'customer_id' => (int) $credit->customer_id,
                'customer_credit_id' => (int) $credit->id,
                'note' => $validated['note'] ?? 'Paiement client sur ardoise.',
            ]);

            return [$credit, $payment];
        });

        return response()->json([
            'data' => [
                'payment' => CreditPaymentResource::make($payment),
                'credit' => CustomerCreditResource::make($credit->refresh()->load([
                    'customer:id,name,phone',
                    'payments' => fn ($query) => $query->orderByDesc('paid_at')->orderByDesc('id'),
                ])),
            ],
        ], 201);
    }
}
