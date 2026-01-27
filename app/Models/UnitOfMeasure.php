<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\UnitOfMeasureObserver;
use Database\Factories\UnitOfMeasureFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $code
 * @property string $label
 * @property bool $is_active
 * @property int $sort_order
 * @property-read string $created_by
 */
#[ObservedBy(UnitOfMeasureObserver::class)]
final class UnitOfMeasure extends Model
{
    use HasCreator;

    /** @use HasFactory<UnitOfMeasureFactory> */
    use HasFactory;

    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
