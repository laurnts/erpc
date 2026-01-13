# Design: Merge ERP Settings into Team Settings

## Architecture Decision

### Storage Approach: JSON Column vs Separate Table

**Decision: JSON column on `teams` table**

| Approach | Pros | Cons |
|----------|------|------|
| JSON column | Simple, no joins, atomic updates | Harder to query individual settings |
| Separate table | Normalized, queryable | Extra join, more complex |
| Spatie Settings per team | Reuses existing pattern | Complex key management |

JSON column is preferred because:
1. ERP settings are always accessed together (not queried individually)
2. Team model is always loaded in tenant context
3. Simpler migration path
4. No additional queries needed

### Data Structure

```php
// App\Data\TeamErpSettings
final class TeamErpSettings extends Data
{
    public function __construct(
        // Company Info
        public string $company_name = '',
        public string $company_address = '',
        public string $company_phone = '',
        public string $company_email = '',

        // Default Settings
        public string $default_currency = 'USD',
        public float $default_tax_percent = 11.0,
        public int $quote_validity_days = 30,
        public int $default_payment_terms_days = 30,
        public bool $prices_include_tax = false,

        // Document Number Prefixes
        public string $request_number_prefix = 'REQ',
        public string $project_number_prefix = 'PRJ',
        public string $buyer_quote_number_prefix = 'BQ',
        public string $buyer_order_number_prefix = 'BO',
        public string $supplier_order_number_prefix = 'PO',
        public string $buyer_invoice_number_prefix = 'INV',
        public string $supplier_invoice_number_prefix = 'SI',
        public string $buyer_payment_number_prefix = 'PAY',
        public string $supplier_payment_number_prefix = 'SP',
    ) {}
}
```

### Team Model Integration

```php
// App\Models\Team
final class Team extends JetstreamTeam
{
    protected $fillable = ['name', 'personal_team', 'erp_settings'];

    protected function casts(): array
    {
        return [
            'personal_team' => 'boolean',
            'erp_settings' => TeamErpSettings::class,
        ];
    }

    public function getErpSettings(): TeamErpSettings
    {
        return $this->erp_settings ?? new TeamErpSettings();
    }
}
```

### Accessing Settings in Observers

Current pattern:
```php
$settings = app(ErpSettings::class);
$prefix = $settings->request_number_prefix;
```

New pattern:
```php
$settings = $model->team->getErpSettings();
$prefix = $settings->request_number_prefix;
```

For observers where the model has `team_id`:
```php
public function creating(Request $request): void
{
    $team = $request->team ?? Team::find($request->team_id);
    $settings = $team?->getErpSettings() ?? new TeamErpSettings();
    // use $settings...
}
```

### UI Component Structure

```
EditTeam (Filament Page)
├── UpdateTeamName (Livewire)
├── UpdateTeamErpSettings (Livewire) ← NEW
│   ├── Company Information Section
│   ├── Default Settings Section
│   └── Document Prefixes Section
├── AddTeamMember (Livewire)
├── PendingTeamInvitations (Livewire)
├── TeamMembers (Livewire)
└── DeleteTeam (Livewire)
```

### Migration Strategy

```php
Schema::table('teams', function (Blueprint $table) {
    $table->json('erp_settings')->default('{}');
});
```

No data migration needed - new teams start with defaults, existing teams get defaults until configured.

### Permission Model

Current:
- `view erp settings` - global permission
- `update erp settings` - global permission

New:
- Team owner can always edit team ERP settings
- Team admin role can edit team ERP settings
- No separate permission needed (inherits from team management)

## Trade-offs

### Accepted Trade-offs

1. **No migration of existing global settings**: Acceptable because ERP system is new and likely no production data exists yet.

2. **Settings not queryable**: We don't need to query "all teams with currency=USD". Settings are always accessed per-team.

3. **Larger Team model**: JSON column adds ~2KB per team. Negligible for expected team counts.

### Rejected Alternatives

1. **Keep global settings + per-team overrides**: Too complex, unclear precedence rules.

2. **Spatie Settings with team prefix**: Keys like `erp.team.{id}.currency` - harder to manage, no type safety.

3. **Separate `team_erp_settings` table**: Over-engineered for the use case.

## Security Considerations

1. **Authorization**: Only team owners/admins can modify ERP settings
2. **Validation**: All settings validated before save (currency codes, numeric ranges)
3. **XSS**: Company name/address sanitized in PDF output

## Testing Strategy

1. **Unit tests**: `TeamErpSettings` DTO creation, defaults, validation
2. **Feature tests**: Livewire component form submission, Team model accessor
3. **Integration tests**: Observer behavior with team settings, PDF generation
