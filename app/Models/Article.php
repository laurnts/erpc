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
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 */
#[ObservedBy(ArticleObserver::class)]
final class Article extends Model implements HasCustomFields
{
    use HasCreator;

    /** @use HasFactory<ArticleFactory> */
    use HasFactory;

    use HasTags;
    use HasTeam;
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
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Article $article): void {
            // Ensure unit is never null or empty
            if (empty($article->unit)) {
                $article->unit = 'pcs';
            }
        });

        static::updating(function (Article $article): void {
            // Ensure unit is never null or empty
            if (empty($article->unit)) {
                $article->unit = 'pcs';
            }
        });
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
            ->withPivot([
                'supplier_sku',
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
