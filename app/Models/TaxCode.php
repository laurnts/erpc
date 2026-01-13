<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\TaxCodeObserver;
use Database\Factories\TaxCodeFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $name
 * @property float $rate
 * @property bool $is_inclusive_default
 * @property bool $is_active
 * @property bool $is_default
 * @property int $sort_order
 * @property-read string $created_by
 */
#[ObservedBy(TaxCodeObserver::class)]
final class TaxCode extends Model
{
    use HasCreator;

    /** @use HasFactory<TaxCodeFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'rate',
        'is_inclusive_default',
        'is_active',
        'is_default',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rate' => 0,
        'is_inclusive_default' => false,
        'is_active' => true,
        'is_default' => false,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_inclusive_default' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the display name with rate.
     */
    public function getDisplayNameAttribute(): string
    {
        return sprintf('%s (%s%%)', $this->name, number_format((float) $this->rate, 2));
    }
}
