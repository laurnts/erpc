<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $buyer_id
 * @property string $transaction_type
 * @property string $amount
 * @property string $available_credit_before
 * @property string $available_credit_after
 * @property string $credit_used_before
 * @property string $credit_used_after
 * @property string|null $related_type
 * @property int|null $related_id
 * @property string|null $description
 * @property int|null $created_by_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Company $buyer
 * @property-read User|null $createdBy
 * @property-read Model|null $related
 */
final class BuyerCreditUsageHistory extends Model
{
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'buyer_id',
        'transaction_type',
        'amount',
        'available_credit_before',
        'available_credit_after',
        'credit_used_before',
        'credit_used_after',
        'related_type',
        'related_id',
        'description',
        'created_by_id',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'available_credit_before' => 'decimal:2',
            'available_credit_after' => 'decimal:2',
            'credit_used_before' => 'decimal:2',
            'credit_used_after' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The buyer this credit usage history belongs to.
     *
     * @return BelongsTo<Company, $this>
     */
    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'buyer_id');
    }

    /**
     * The user who created this history record.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * The related entity (e.g., BuyerOrder) that triggered this credit usage.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
