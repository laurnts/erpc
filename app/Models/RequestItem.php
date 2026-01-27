<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Enums\Unit;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $description
 * @property string $quantity
 * @property Unit $unit
 * @property string|null $notes
 * @property int $sort_order
 * @property bool $is_matched
 * @property int $request_id
 * @property int|null $article_id
 * @property int|null $supplier_id
 * @property-read Request $request
 * @property-read Article|null $article
 * @property-read Company|null $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SupplierQuoteItem> $supplierQuoteItems
 * @property-read int $supplier_quote_items_count
 */
final class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'article_id',
        'supplier_id',
        'description',
        'quantity',
        'unit',
        'unit_of_measure_id',
        'notes',
        'sort_order',
        'is_matched',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'unit' => 'pcs',
        'sort_order' => 0,
        'is_matched' => false,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit' => SafeUnitCast::class,
            'sort_order' => 'integer',
            'is_matched' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (RequestItem $item): void {
            // Ensure unit is never null or empty
            if (empty($item->unit)) {
                $item->unit = 'pcs';
            }
        });

        static::updating(function (RequestItem $item): void {
            // Ensure unit is never null or empty
            if (empty($item->unit)) {
                $item->unit = 'pcs';
            }
        });
    }

    /**
     * The request that owns this item.
     *
     * @return BelongsTo<Request, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    /**
     * The unit of measure for this item.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * The article matched to this item.
     *
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * The supplier assigned to fulfill this item.
     *
     * @return BelongsTo<Company, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'supplier_id');
    }

    /**
     * The supplier quote items referencing this request item.
     *
     * @return HasMany<SupplierQuoteItem, $this>
     */
    public function supplierQuoteItems(): HasMany
    {
        return $this->hasMany(SupplierQuoteItem::class);
    }

    /**
     * Match this item to an article and optionally assign a supplier.
     */
    public function matchToArticle(Article $article, ?Company $supplier = null): void
    {
        $this->article_id = $article->getKey();
        $this->supplier_id = $supplier?->getKey();
        $this->is_matched = true;
        $this->save();
    }

    /**
     * Unmatch this item from its article and clear supplier.
     */
    public function unmatch(): void
    {
        $this->article_id = null;
        $this->supplier_id = null;
        $this->is_matched = false;
        $this->save();
    }

    /**
     * Get the display text for this item.
     */
    public function getDisplayTextAttribute(): string
    {
        if ($this->article !== null) {
            return sprintf('[%s] %s', $this->article->code, $this->article->name);
        }

        return $this->description;
    }
}
