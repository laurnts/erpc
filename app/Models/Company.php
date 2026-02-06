<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreationSource;
use App\Enums\DeliveryType;
use App\Models\Concerns\HasAiSummary;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasNotes;
use App\Models\Concerns\HasTags;
use App\Models\Concerns\HasTeam;
use App\Observers\CompanyObserver;
use App\Services\AvatarService;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Relaticle\CustomFields\Models\Concerns\UsesCustomFields;
use Relaticle\CustomFields\Models\Contracts\HasCustomFields;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $creator_id
 * @property int|null $account_owner_id
 * @property int|null $default_currency_id
 * @property string $code
 * @property string $name
 * @property string|null $contact_person @deprecated Use company_people pivot with is_primary flag instead. Access via $company->primaryContact()->first()
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $country
 * @property string|null $domain
 * @property bool $is_buyer
 * @property bool $is_supplier
 * @property string $credit_limit
 * @property string $available_credit
 * @property string|null $requested_credit_limit
 * @property string $credit_used
 * @property bool $is_on_hold
 * @property string|null $on_hold_reason
 * @property bool $credit_status
 * @property int $lead_time_days
 * @property DeliveryType|null $delivery_type
 * @property DeliveryType|null $delivery_type_details
 * @property bool $is_taxable
 * @property string|null $delivery_term
 * @property int $payment_terms_days
 * @property string|null $internal_notes
 * @property bool $is_active
 * @property CreationSource $creation_source
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read string $created_by
 */
#[ObservedBy(CompanyObserver::class)]
final class Company extends Model implements HasCustomFields, HasMedia
{
    use HasAiSummary;
    use HasCreator;

    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    use HasNotes;
    use HasTags;
    use HasTeam;
    use InteractsWithMedia;
    use SoftDeletes;
    use UsesCustomFields;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'contact_person',
        'email',
        'phone',
        'address',
        'country',
        'domain',
        'is_buyer',
        'is_supplier',
        'credit_limit',
        'available_credit',
        'requested_credit_limit',
        'credit_used',
        'is_on_hold',
        'on_hold_reason',
        'credit_status',
        'lead_time_days',
        'delivery_type',
        'delivery_type_details',
        'is_taxable',
        'delivery_term',
        'payment_terms_days',
        'internal_notes',
        'is_active',
        'default_currency_id',
        'creation_source',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_buyer' => false,
        'is_supplier' => false,
        'credit_limit' => 0,
        'available_credit' => 0,
        'credit_used' => 0,
        'is_on_hold' => false,
        'credit_status' => true,
        'lead_time_days' => 0,
        'is_taxable' => true,
        'payment_terms_days' => 30,
        'is_active' => true,
        'creation_source' => CreationSource::WEB,
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string|class-string|\Illuminate\Contracts\Database\Eloquent\CastsAttributes>
     */
    protected function casts(): array
    {
        return [
            'is_buyer' => 'boolean',
            'is_supplier' => 'boolean',
            'credit_limit' => 'decimal:2',
            'available_credit' => 'decimal:2',
            'requested_credit_limit' => 'decimal:2',
            'credit_used' => 'decimal:2',
            'is_on_hold' => 'boolean',
            'credit_status' => 'boolean',
            'lead_time_days' => 'integer',
            'is_taxable' => 'boolean',
            'payment_terms_days' => 'integer',
            'is_active' => 'boolean',
            'creation_source' => CreationSource::class,
            // Use SafeDeliveryTypeCast to handle invalid enum values gracefully (e.g., "Franco")
            // This prevents errors when loading models with invalid delivery_type values
            'delivery_type' => \App\Casts\SafeDeliveryTypeCast::class,
        ];
    }

    public function getLogoAttribute(): string
    {
        $logo = $this->getFirstMediaUrl('logo');

        return $logo === '' || $logo === '0' ? app(AvatarService::class)->generateAuto(name: $this->name) : $logo;
    }


    /**
     * Team member responsible for managing the company account.
     *
     * @return BelongsTo<User, $this>
     */
    public function accountOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_owner_id');
    }

    /**
     * Get the default currency for this company.
     *
     * @return BelongsTo<Currency, $this>
     */
    public function defaultCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'default_currency_id');
    }

    /**
     * Get all people associated with this company.
     *
     * @return BelongsToMany<People, $this>
     */
    public function people(): BelongsToMany
    {
        return $this->belongsToMany(People::class)
            ->using(CompanyPeople::class)
            ->withPivot(['role', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Get the primary contact person for this company.
     *
     * @return BelongsToMany<People, $this>
     */
    public function primaryContact(): BelongsToMany
    {
        return $this->people()->wherePivot('is_primary', true)->limit(1);
    }

    /**
     * @return HasMany<Opportunity, $this>
     */
    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    /**
     * Key accounts (team members with Central Purchasing Key Account role) assigned to handle this buyer.
     *
     * @return BelongsToMany<User, $this>
     */
    public function keyAccounts(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'key_account_buyers', 'buyer_id', 'key_account_id')
            ->whereHas('teams', function ($query) {
                $query->where('team_user.role', 'central_purchasing')
                    ->where('team_user.central_purchasing_role', \App\Enums\CentralPurchasingRole::KEY_ACCOUNT->value);
            })
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Task, $this>
     */
    public function tasks(): MorphToMany
    {
        return $this->morphToMany(Task::class, 'taskable');
    }

    /**
     * Get all articles supplied by this company (when acting as supplier).
     *
     * @return BelongsToMany<Article, $this>
     */
    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'supplier_articles', 'supplier_id', 'article_id')
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
     * Get all credit limit requests for this buyer.
     *
     * @return HasMany<BuyerCreditLimitRequest, $this>
     */
    public function creditLimitRequests(): HasMany
    {
        return $this->hasMany(BuyerCreditLimitRequest::class, 'buyer_id');
    }

    /**
     * Get all credit usage history records for this buyer.
     *
     * @return HasMany<BuyerCreditUsageHistory, $this>
     */
    public function creditUsageHistory(): HasMany
    {
        return $this->hasMany(BuyerCreditUsageHistory::class, 'buyer_id');
    }

    /**
     * Get the current pending credit limit request for this buyer.
     *
     * @return BuyerCreditLimitRequest|null
     */
    public function pendingCreditLimitRequest(): ?BuyerCreditLimitRequest
    {
        return $this->creditLimitRequests()
            ->where('status', \App\Enums\CreditLimitRequestStatus::PENDING)
            ->latest()
            ->first();
    }

    /**
     * Generate the next company code for the given team.
     */
    public static function generateNextCode(int $teamId): string
    {
        $lastCompany = self::withTrashed()
            ->where('team_id', $teamId)
            ->orderByRaw('CAST(SUBSTRING(code, 5) AS INTEGER) DESC')
            ->first();

        if ($lastCompany === null) {
            return 'CMP-0001';
        }

        $lastNumber = (int) mb_substr((string) $lastCompany->code, 4);
        $nextNumber = $lastNumber + 1;

        return 'CMP-'.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
