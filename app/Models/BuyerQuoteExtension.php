<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BuyerQuoteExtensionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $buyer_quote_id
 * @property int|null $extended_by_id
 * @property Carbon $original_valid_until
 * @property Carbon $new_valid_until
 * @property string|null $reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BuyerQuote $buyerQuote
 * @property-read User|null $extendedBy
 * @property-read int $extension_days
 */
final class BuyerQuoteExtension extends Model
{
    /** @use HasFactory<BuyerQuoteExtensionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_quote_id',
        'extended_by_id',
        'original_valid_until',
        'new_valid_until',
        'reason',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'original_valid_until' => 'date',
            'new_valid_until' => 'date',
        ];
    }

    /**
     * The buyer quote this extension is for.
     *
     * @return BelongsTo<BuyerQuote, $this>
     */
    public function buyerQuote(): BelongsTo
    {
        return $this->belongsTo(BuyerQuote::class);
    }

    /**
     * The user who extended the validity.
     *
     * @return BelongsTo<User, $this>
     */
    public function extendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'extended_by_id');
    }

    /**
     * Get the number of days the quote was extended by.
     */
    public function getExtensionDaysAttribute(): int
    {
        return (int) $this->original_valid_until->diffInDays($this->new_valid_until);
    }

    /**
     * Get a summary of the extension for display.
     */
    public function getSummaryAttribute(): string
    {
        $days = $this->extension_days;
        $extendedBy = $this->extendedBy->name ?? 'Unknown';

        return sprintf(
            'Extended by %d day%s by %s on %s',
            $days,
            $days === 1 ? '' : 's',
            $extendedBy,
            $this->created_at?->format('M j, Y') ?? 'unknown date'
        );
    }
}
