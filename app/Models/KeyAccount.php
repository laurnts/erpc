<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasTeam;
use App\Observers\KeyAccountObserver;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Key Account personnel for approval workflows.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $is_active
 * @property int|null $creator_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $created_by
 * @property-read string $display_name
 */
#[ObservedBy(KeyAccountObserver::class)]
final class KeyAccount extends Model
{
    use HasCreator;
    use HasTeam;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Quotation Evaluations where this person is the preparer.
     *
     * @return HasMany<QuotationEvaluation, $this>
     */
    public function preparedEvaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class, 'prepared_by_id');
    }

    /**
     * Quotation Evaluations where this person is the approver.
     *
     * @return HasMany<QuotationEvaluation, $this>
     */
    public function approvedEvaluations(): HasMany
    {
        return $this->hasMany(QuotationEvaluation::class, 'approved_by_id');
    }

    /**
     * Buyers assigned to this key account.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'key_account_buyers', 'key_account_id', 'buyer_id')
            ->where('is_buyer', true)
            ->withTimestamps();
    }

    /**
     * Get display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Scope to active key accounts for the current Filament tenant.
     *
     * @param  Builder<KeyAccount>  $query
     * @return Builder<KeyAccount>
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function activeForCurrentTeam(Builder $query): Builder
    {
        $teamId = Filament::getTenant()?->getKey();

        return $query
            ->where('is_active', true)
            ->when($teamId !== null, fn (Builder $q): Builder => $q->where('team_id', $teamId));
    }

    /**
     * Get select options formatted for Filament select fields.
     *
     * @param  int|null  $buyerId  Optional buyer ID to filter key accounts assigned to handle that buyer
     * @return array<int, string>
     */
    public static function selectOptions(?int $buyerId = null): array
    {
        return self::activeForCurrentTeam()
            ->when($buyerId !== null, fn (Builder $q): Builder => $q->whereHas(
                'buyers',
                fn (Builder $bq): Builder => $bq->where('companies.id', $buyerId)
            ))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }
}
