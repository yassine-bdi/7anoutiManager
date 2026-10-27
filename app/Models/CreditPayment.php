<?php

namespace App\Models;

use Database\Factories\CreditPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['customer_credit_id', 'amount', 'paid_at', 'note'])]
class CreditPayment extends Model
{
    /** @use HasFactory<CreditPaymentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<CustomerCredit, $this>
     */
    public function credit(): BelongsTo
    {
        return $this->belongsTo(CustomerCredit::class, 'customer_credit_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'paid_at' => 'datetime',
        ];
    }
}
