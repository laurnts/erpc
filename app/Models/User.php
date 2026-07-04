<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PortalType;
use App\Models\Concerns\HasProfilePhoto;
use Database\Factories\UserFactory;
use Exception;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $name
 * @property string $email
 * @property string|null $password
 * @property string|null $profile_photo_path
 * @property-read string $profile_photo_url
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property string|null $two_factor_recovery_codes
 * @property string|null $two_factor_secret
 */
final class User extends Authenticatable implements FilamentUser, HasAvatar, HasDefaultTenant, HasTenants, MustVerifyEmail
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasProfilePhoto;
    use HasRoles;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url', // @phpstan-ignore rules.modelAppends
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return HasMany<UserSocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(UserSocialAccount::class);
    }

    /**
     * Buyers assigned to this key account user.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(
            Company::class,
            'key_account_buyers',
            'key_account_id',
            'buyer_id'
        )
            ->where('companies.is_buyer', true)
            ->withTimestamps();
    }

    /**
     * Buyer companies this user can access via the customer portal.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function portalCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_portal_users')
            ->withPivot(['team_id', 'portal', 'invited_by', 'is_active'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<CompanyPortalUser, $this>
     */
    public function portalMemberships(): HasMany
    {
        return $this->hasMany(CompanyPortalUser::class);
    }

    public function hasActiveBuyerPortalAccess(): bool
    {
        return $this->activeCustomerPortalMembershipsQuery()->exists();
    }

    public function hasActiveSupplierPortalAccess(): bool
    {
        return $this->activeSupplierPortalMembershipsQuery()->exists();
    }

    public function belongsToAnyInternalTeam(): bool
    {
        return $this->allTeams()->isNotEmpty();
    }

    /**
     * @return list<int>
     */
    public function activeCustomerPortalCompanyIds(): array
    {
        return $this->activeCustomerPortalMembershipsQuery()
            ->pluck('company_id')
            ->all();
    }

    /**
     * @return list<int>
     */
    public function activeSupplierPortalCompanyIds(): array
    {
        /** @var list<int> $companyIds */
        $companyIds = $this->activeSupplierPortalMembershipsQuery()
            ->pluck('company_id')
            ->all();

        return $companyIds;
    }

    /**
     * Customer portal capability requires an explicit customer-typed membership
     * AND the company actually being a buyer — a supplier-only membership must
     * never grant customer panel access.
     *
     * @return HasMany<CompanyPortalUser, $this>
     */
    private function activeCustomerPortalMembershipsQuery(): HasMany
    {
        return $this->portalMemberships()
            ->where('is_active', true)
            ->where('portal', PortalType::Customer)
            ->whereHas('company', fn ($query) => $query->where('is_buyer', true));
    }

    /**
     * Supplier portal capability requires an explicit supplier-typed membership
     * AND the company actually being a supplier — a customer-only membership
     * must never grant supplier panel access, including at dual-role companies.
     *
     * @return HasMany<CompanyPortalUser, $this>
     */
    private function activeSupplierPortalMembershipsQuery(): HasMany
    {
        return $this->portalMemberships()
            ->where('is_active', true)
            ->where('portal', PortalType::Supplier)
            ->whereHas('company', fn ($query) => $query->where('is_supplier', true));
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->currentTeam;
    }

    /**
     * @throws Exception
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->hasVerifiedEmail()) {
            return false;
        }

        return match ($panel->getId()) {
            'app' => $this->belongsToAnyInternalTeam(),
            'customer' => $this->hasActiveBuyerPortalAccess(),
            'supplier' => $this->hasActiveSupplierPortalAccess(),
            default => false,
        };
    }

    /**
     * @return Collection<int, Team>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->allTeams();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->belongsToTeam($tenant);
    }
}
