<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $team_id
 * @property string $type
 * @property string $name
 * @property string $content
 * @property string|null $sender_email
 * @property array|null $cc_emails
 * @property array|null $bcc_emails
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class EmailTemplate extends Model
{
    /**
     * Template types
     */
    public const string TYPE_BUYER_QUOTE = 'buyer_quote';

    public const string TYPE_BUYER_ORDER = 'buyer_order';

    public const string TYPE_SUPPLIER_ORDER = 'supplier_order';

    public const string TYPE_DELIVERY_ORDER = 'delivery_order';

    public const string TYPE_QUOTATION_EVALUATION = 'quotation_evaluation';

    public const string TYPE_PROFIT_AND_LOSS = 'profit_and_loss';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'type',
        'name',
        'content',
        'sender_email',
        'cc_emails',
        'bcc_emails',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'cc_emails' => 'array',
        'bcc_emails' => 'array',
        'is_default' => 'boolean',
    ];

    /**
     * Get the team that owns the template.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope a query to only include templates for a specific team.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forTeam(Builder $query, ?Team $team): Builder
    {
        if (! $team instanceof \App\Models\Team) {
            return $query->whereNull('team_id');
        }

        return $query->where(function (Builder $q) use ($team): void {
            $q->where('team_id', $team->id)
                ->orWhereNull('team_id'); // Include default templates
        });
    }

    /**
     * Scope a query to only include default templates.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function defaults(Builder $query): Builder
    {
        return $query->where('is_default', true)
            ->whereNull('team_id');
    }

    /**
     * Scope a query to only include templates of a specific type.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function forType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Get all available template types.
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_BUYER_QUOTE,
            self::TYPE_BUYER_ORDER,
            self::TYPE_SUPPLIER_ORDER,
            self::TYPE_DELIVERY_ORDER,
            self::TYPE_QUOTATION_EVALUATION,
            self::TYPE_PROFIT_AND_LOSS,
        ];
    }
}
