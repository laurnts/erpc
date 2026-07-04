<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SupplierArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Supplier-article link. `supplier_price` (+ currency) is the supplier-owned,
 * forward-looking standing offer; `last_quoted_*` is the staff-owned,
 * backward-looking RFQ history — they are deliberately separate fields.
 *
 * @property int $id
 * @property int $article_id
 * @property int $supplier_id
 * @property string|null $supplier_sku
 * @property numeric-string|null $supplier_price
 * @property int|null $supplier_price_currency_id
 * @property Carbon|null $supplier_price_updated_at
 * @property numeric-string|null $available_quantity
 * @property Carbon|null $quantity_updated_at
 * @property numeric-string|null $last_quoted_price
 * @property int|null $last_quoted_currency_id
 * @property Carbon|null $last_quoted_at
 * @property int|null $lead_time_days
 * @property string|null $notes
 * @property bool $is_preferred
 * @property bool $is_active
 */
final class SupplierArticle extends Pivot
{
    /** @use HasFactory<SupplierArticleFactory> */
    use HasFactory;

    protected $table = 'supplier_articles';

    public $incrementing = true;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'article_id',
        'supplier_id',
        'supplier_sku',
        'supplier_price',
        'supplier_price_currency_id',
        'supplier_price_updated_at',
        'available_quantity',
        'quantity_updated_at',
        'last_quoted_price',
        'last_quoted_currency_id',
        'last_quoted_at',
        'lead_time_days',
        'notes',
        'is_preferred',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_price' => 'decimal:4',
            'supplier_price_updated_at' => 'datetime',
            'available_quantity' => 'decimal:4',
            'quantity_updated_at' => 'datetime',
            'last_quoted_price' => 'decimal:2',
            'last_quoted_at' => 'datetime',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $supplierArticle): void {
            if ($supplierArticle->isDirty(['supplier_price', 'supplier_price_currency_id'])) {
                $supplierArticle->supplier_price_updated_at = now();
            }

            if ($supplierArticle->isDirty('available_quantity')) {
                $supplierArticle->quantity_updated_at = now();
            }
        });
    }

    /**
     * Own-company scoping for supplier portal surfaces — the single source of
     * truth for "rows this supplier may see" (never inline where-clauses).
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForSupplier(Builder $query, int $supplierCompanyId): Builder
    {
        return $query->where('supplier_id', $supplierCompanyId);
    }

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function supplierPriceCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'supplier_price_currency_id');
    }
}
