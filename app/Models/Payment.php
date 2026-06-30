<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment transaction attempt for an invoice.
 *
 * @since 1.0.0
 */
class Payment extends Model
{
    use HasFactory, HasUuid;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'invoice_id',
        'payment_gateway_id',
        'status',
        'amount',
        'currency',
        'authority',
        'reference_id',
        'idempotency_key',
        'gateway_response_json',
        'verified_at',
    ];

    /**
     * Clinic tenant connected to this payment.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    protected $casts = [
        'gateway_response_json' => 'array',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * User that initiated this payment.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Invoice being paid.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Gateway used for this payment.
     *
     * @return BelongsTo<PaymentGateway, $this>
     */
    public function gateway(): BelongsTo
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }
}
