# public-catalog Delta

## REMOVED Requirements

### Requirement: Product Detail Page
**Reason**: Product-owner decision — the grid card already presents all public information and the add-to-quote control; a separate page added a navigation hop without new information. May return as a future change if per-article galleries/attributes justify a page.
**Migration**: The `/articles/{article}` route now returns 404; grid cards no longer link out.

## MODIFIED Requirements

### Requirement: Public Product Grid Homepage
The system SHALL serve a public, unauthenticated product catalog at `/`, replacing the static marketing homepage, showing only articles of the configured catalog team that are active and explicitly published to the grid.

#### Scenario: Visitor browses the grid
- **WHEN** a guest visits `/`
- **THEN** a paginated product grid renders articles where `is_active = true` AND `show_in_product_grid = true`, scoped to the catalog team
- **AND** each card shows the primary product image (or a placeholder), name, category tags, price display, availability badge, and a quantity input with an add-to-quote control

#### Scenario: Unpublished articles are invisible
- **WHEN** an article has `show_in_product_grid = false` or `is_active = false`
- **THEN** it does not appear in the grid, in search results, or in category counts

#### Scenario: Catalog team resolution
- **WHEN** the public catalog resolves its team
- **THEN** it uses `config('catalog.team_id')`, falling back to the first team
- **AND** it never depends on `auth()` or Filament tenancy (guests must not trigger `TeamScope`)

### Requirement: Public Price Display
The system SHALL display an article's `list_price` in the team default currency when set, and "Price on request" when not set, and SHALL never expose supplier costs or margins publicly.

#### Scenario: Price set
- **WHEN** an article has a `list_price`
- **THEN** the grid card shows it formatted with the team default currency

#### Scenario: Price not set
- **WHEN** `list_price` is null
- **THEN** "Price on request" is shown instead of a number

#### Scenario: No cost leakage
- **WHEN** any public page renders
- **THEN** supplier identities, `last_quoted_price`, margins, and article codes are absent from markup and responses
