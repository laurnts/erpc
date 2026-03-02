<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_quote_id
 * @property int $due_days
 * @property int $percentage
 * @property int|null $job_progress
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read SupplierQuote $supplierQuote
 */
final class SupplierQuotePaymentTerm extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_quote_id',
        'due_days',
        'percentage',
        'job_progress',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'due_days' => 0,
        'percentage' => 0,
        'job_progress' => null,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_days' => 'integer',
            'percentage' => 'integer',
            'job_progress' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * The supplier quote this payment term belongs to.
     *
     * @return BelongsTo<SupplierQuote, $this>
     */
    public function supplierQuote(): BelongsTo
    {
        return $this->belongsTo(SupplierQuote::class);
    }
}
