<?php

declare(strict_types=1);

namespace App\Services\Catalog;

/**
 * Result of resolving an article's best supplier cost in the team default
 * currency. `convertedCost === null` with `hasCostData === true` means cost
 * data exists but could not be converted (missing exchange rates).
 */
final readonly class ArticleCostResolution
{
    /**
     * @param  list<string>  $notices
     */
    public function __construct(
        public ?float $convertedCost,
        public bool $hasCostData,
        public array $notices,
    ) {}
}
