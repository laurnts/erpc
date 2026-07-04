<?php

declare(strict_types=1);

namespace App\Models;

use App\Data\TeamErpSettings;
use App\Services\AvatarService;
use Database\Factories\TeamFactory;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property string $name
 * @property TeamErpSettings|null $erp_settings
 */
final class Team extends JetstreamTeam implements HasAvatar, HasMedia
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'personal_team',
        'erp_settings',
    ];

    /**
     * The event map for the model.
     *
     * @var array<string, class-string>
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'erp_settings' => TeamErpSettings::class,
        ];
    }

    public function isPersonalTeam(): bool
    {
        return $this->personal_team;
    }

    /**
     * Get the ERP settings for this team with defaults.
     */
    public function getErpSettings(): TeamErpSettings
    {
        return $this->erp_settings ?? new TeamErpSettings;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('company_logo')->singleFile();
        $this->addMediaCollection('favicon')->singleFile();
        $this->addMediaCollection('email_logo')->singleFile();
    }

    public function getCompanyLogoUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('company_logo');

        return $url === '' || $url === '0' ? null : $url;
    }

    public function getFaviconUrl(): ?string
    {
        $url = $this->getFirstMediaUrl('favicon');

        return $url === '' || $url === '0' ? null : $url;
    }

    public function getFilamentAvatarUrl(): string
    {
        return $this->getCompanyLogoUrl()
            ?? app(AvatarService::class)->generate(name: $this->name, bgColor: '#000000', textColor: '#ffffff');
    }

    /**
     * @return HasMany<People, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(People::class);
    }

    /**
     * @return HasMany<Company, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    /**
     * Get the team's base currency model.
     */
    public function getBaseCurrency(): ?Currency
    {
        $code = $this->getErpSettings()->default_currency;

        return Currency::query()
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get the team's base currency code.
     */
    public function getBaseCurrencyCode(): string
    {
        return $this->getErpSettings()->default_currency;
    }

    /**
     * Format an amount using the team's base currency formatting.
     * Example: For IDR 10000 → "Rp 10.000,-"
     */
    public function formatMoney(float|int $amount): string
    {
        $currency = $this->getBaseCurrency();

        if (! $currency instanceof \App\Models\Currency) {
            return number_format((float) $amount, 2);
        }

        return $currency->format($amount);
    }

    /**
     * Get the email logo URL if configured.
     */
    public function getEmailLogoUrl(): ?string
    {
        return $this->getCompanyLogoUrl() ?? $this->getLegacyEmailLogoUrl();
    }

    private function getLegacyEmailLogoUrl(): ?string
    {
        $settings = $this->getErpSettings();

        if (! $settings->email_logo_media_id) {
            return null;
        }

        $media = $this->getMedia('email_logo')
            ->firstWhere('id', $settings->email_logo_media_id);

        if (! $media instanceof Media) {
            return null;
        }

        return $media->getUrl();
    }
}
