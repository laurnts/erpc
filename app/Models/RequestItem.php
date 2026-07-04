<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Enums\ItemType;
use App\Enums\Unit;
use App\Observers\RequestItemObserver;
use Database\Factories\RequestItemFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $description
 * @property ItemType $item_type
 * @property string $quantity
 * @property Unit|string $unit
 * @property string|null $notes
 * @property int $sort_order
 * @property bool $is_matched
 * @property int $request_id
 * @property int|null $parent_id
 * @property int|null $article_id
 * @property int|null $supplier_id
 * @property int|null $unit_of_measure_id
 * @property-read Request $request
 * @property-read RequestItem|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RequestItem> $children
 * @property-read Article|null $article
 * @property-read Company|null $supplier
 * @property-read \Illuminate\Database\Eloquent\Collection<int, SupplierQuoteItem> $supplierQuoteItems
 * @property-read int $supplier_quote_items_count
 */
#[ObservedBy(RequestItemObserver::class)]
final class RequestItem extends Model
{
    /** @use HasFactory<RequestItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'request_id',
        'parent_id',
        'article_id',
        'supplier_id',
        'description',
        'item_type',
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
        'item_type' => 'goods',
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
            'item_type' => ItemType::class,
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

        self::creating(function (RequestItem $item): void {
            // Ensure unit is never null or empty
            if (empty($item->unit)) {
                $item->unit = 'pcs';
            }

            // Child items always fulfill through their parent's channel
            if ($item->parent_id !== null) {
                $parentType = self::query()->whereKey($item->parent_id)->value('item_type');
                if ($parentType !== null) {
                    $item->item_type = $parentType;
                }
            }
        });

        self::updating(function (RequestItem $item): void {
            // Ensure unit is never null or empty
            if (empty($item->unit)) {
                $item->unit = 'pcs';
            }
        });

        self::updated(function (RequestItem $item): void {
            // Keep the child-inherits-parent-type invariant on type changes
            if ($item->wasChanged('item_type') && $item->parent_id === null) {
                $item->children()->update(['item_type' => $item->item_type]);
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
     * The parent item (for child items in Service requests).
     *
     * @return BelongsTo<RequestItem, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class, 'parent_id');
    }

    /**
     * The child items (for main items in Service requests).
     *
     * @return HasMany<RequestItem, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(RequestItem::class, 'parent_id')->orderBy('sort_order');
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

    /**
     * Check if this is a main item (has no parent).
     */
    public function isMainItem(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this is a child item (has a parent).
     */
    public function isChildItem(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Get the main item (self if main item, parent if child item).
     */
    public function getMainItem(): self
    {
        if ($this->isMainItem()) {
            return $this;
        }

        return $this->parent;
    }

    /**
     * Whether this item can carry a child-item breakdown.
     */
    public function supportsItemHierarchy(): bool
    {
        return $this->item_type->supportsItemHierarchy();
    }

    /**
     * Whether this item fulfills via acceptance reports.
     */
    public function usesAcceptanceReports(): bool
    {
        return $this->item_type->usesAcceptanceReports();
    }

    /**
     * Whether this item fulfills via physical shipments.
     */
    public function requiresShipments(): bool
    {
        return $this->item_type->requiresShipments();
    }
}
