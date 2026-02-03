<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $buyer_credit_limit_request_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $approved_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read BuyerCreditLimitRequest $buyerCreditLimitRequest
 * @property-read User $user
 */
final class BuyerCreditLimitRequestApproval extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'buyer_credit_limit_request_id',
        'user_id',
        'approved_at',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<BuyerCreditLimitRequest, $this>
     */
    public function buyerCreditLimitRequest(): BelongsTo
    {
        return $this->belongsTo(BuyerCreditLimitRequest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
