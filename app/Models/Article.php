<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SafeUnitCast;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTeam;
use App\Observers\ArticleObserver;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string|null $sku
 * @property string $unit
 * @property int|null $default_tax_code_id
 * @property array<string, mixed>|null $attributes
 * @property string|null $notes
 * @property bool $is_active
 * @property numeric-string|null $list_price
 * @property Carbon|null $list_price_updated_at
 * @property bool $show_in_product_grid
 * @property bool $price_review_needed
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 */
#[ObservedBy(ArticleObserver::class)]
final class Article extends Model implements HasCustomFields, HasMedia
{
    use HasCreator;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use HasTags;
    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'sku',
        'unit',
        'unit_of_measure_id',
        'default_tax_code_id',
        'attributes',
        'notes',
        'is_active',
        'list_price',
        'show_in_product_grid',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => 'pcs',
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'unit' => SafeUnitCast::class,
            'attributes' => 'array',
            'is_active' => 'boolean',
            'list_price' => 'decimal:4',
            'list_price_updated_at' => 'datetime',
            'show_in_product_grid' => 'boolean',
            'price_review_needed' => 'boolean',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        self::creating(function (Article $article): void {
            // Ensure unit is never null or empty
            if (empty($article->unit)) {
                $article->unit = 'pcs';
            }
        });

        self::updating(function (Article $article): void {
            // Ensure unit is never null or empty
            if (empty($article->unit)) {
                $article->unit = 'pcs';
            }
        });

        self::saving(function (Article $article): void {
            // Saving a changed list_price is the publish act: stamp the
            // publication time and clear any pending price review flag.
            if ($article->isDirty('list_price')) {
                $article->list_price_updated_at = now();
                $article->price_review_needed = false;
            }
        });
    }

    /**
     * Register media collections for this model.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('product_images')
            ->acceptsMimeTypes([
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif',
            ]);
    }

    /**
     * Register media conversions for product images.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->performOnCollections('product_images')
            ->nonQueued()
            ->fit(Fit::Crop, 150, 150);

        $this->addMediaConversion('medium')
            ->performOnCollections('product_images')
            ->nonQueued()
            ->fit(Fit::Contain, 800, 800);
    }

    /**
     * Get the unit of measure for this article.
     *
     * @return BelongsTo<UnitOfMeasure, $this>
     */
    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class);
    }

    /**
     * Get the default tax code for this article.
     *
     * @return BelongsTo<TaxCode, $this>
     */
    public function defaultTaxCode(): BelongsTo
    {
        return $this->belongsTo(TaxCode::class, 'default_tax_code_id');
    }

    /**
     * Get all suppliers for this article.
     * Suppliers are Companies with is_supplier = true.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'supplier_articles', 'article_id', 'supplier_id')
            ->where('is_supplier', true)
            ->using(SupplierArticle::class)
            ->withPivot([
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
            ])
            ->withTimestamps();
    }

    /**
     * Alias for suppliers() method.
     * Some form fields may reference this relationship as "companies".
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->suppliers();
    }

    /**
     * Get the preferred supplier for this article.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function preferredSupplier(): BelongsToMany
    {
        return $this->suppliers()->wherePivot('is_preferred', true)->limit(1);
    }

    /**
     * Get the display name with code.
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf('[%s] %s', $this->code, $this->name);
    }
}
