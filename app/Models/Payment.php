<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_id',
        'transaction_id',
        'amount',
        'payment_method',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER / USER
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            Order::class,
            'order_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return strtolower(
            trim(
                (string) $this->status
            )
        ) === 'pending';
    }

    public function isPaid(): bool
    {
        return strtolower(
            trim(
                (string) $this->status
            )
        ) === 'paid';
    }

    public function isFailed(): bool
    {
        return strtolower(
            trim(
                (string) $this->status
            )
        ) === 'failed';
    }

    public function isRefunded(): bool
    {
        return strtolower(
            trim(
                (string) $this->status
            )
        ) === 'refunded';
    }

    /*
    |--------------------------------------------------------------------------
    | CUSTOMER HAS PAID
    |--------------------------------------------------------------------------
    */

    public function hasCustomerPaid(): bool
    {
        return $this->isPaid();
    }

    /*
    |--------------------------------------------------------------------------
    | FORMATTED PAID DATE
    |--------------------------------------------------------------------------
    */

    public function getFormattedPaidAtAttribute(): ?string
    {
        return $this->paid_at
            ? $this->paid_at->format(
                'd M Y, h:i A'
            )
            : null;
    }
}

