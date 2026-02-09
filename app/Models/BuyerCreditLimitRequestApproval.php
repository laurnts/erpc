<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $buyer_credit_limit_request_id
 * @property int $user_id
 * @property \Illuminate\Support\Carbon $approved_at
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read BuyerCreditLimitRequest $buyerCreditLimitRequest
 * @property-read User $user
 * @property-read Team $team
 */
final class BuyerCreditLimitRequestApproval extends Model
{
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
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
