# public-catalog Specification

## Purpose
TBD - created by archiving change add-public-product-catalog. Update Purpose after archive.
## Requirements
### Requirement: Public Product Grid Homepage
The system SHALL serve a public, unauthenticated product catalog at `/`, replacing the static marketing homepage, showing only articles of the configured catalog team that are active and explicitly published to the grid.

#### Scenario: Visitor browses the grid
- **WHEN** a guest visits `/`
- **THEN** a paginated product grid renders articles where `is_active = true` AND `show_in_product_grid = true`, scoped to the catalog team
- **AND** each card shows the primary product image (or a placeholder), name, category tags, price display, availability badge, and a quantity input with an add-to-quote control

#### Scenario: Unpublished articles are invisible
- **WHEN** an article has `show_in_product_grid = false` or `is_active = false`
- **THEN** it does not appear in the grid, in search results, in category counts, or via its detail URL (404)

#### Scenario: Catalog team resolution
- **WHEN** the public catalog resolves its team
- **THEN** it uses `config('catalog.team_id')`, falling back to the first team
- **AND** it never depends on `auth()` or Filament tenancy (guests must not trigger `TeamScope`)

### Requirement: Category Menu from Tags
The system SHALL render a category navigation menu built from the tags attached to grid-visible articles, allowing visitors to filter the grid by category.

#### Scenario: Menu shows only used categories
- **WHEN** the homepage renders
- **THEN** the category menu lists only tags attached to at least one grid-visible article of the catalog team

#### Scenario: Filter by category
- **WHEN** a visitor selects a category
- **THEN** the grid shows only grid-visible articles tagged with it, combinable with the current search term

### Requirement: Catalog Search
The system SHALL provide a prominent search bar beneath the category menu that filters the grid by name, SKU, and description without a full page reload.

#### Scenario: Search matches
- **WHEN** a visitor types a term
- **THEN** the grid updates (debounced) to articles whose name, SKU, or description matches, within the current category filter

#### Scenario: No results
- **WHEN** no grid-visible article matches
- **THEN** an empty state with a clear-search affordance is shown

### Requirement: Product Detail Page
The system SHALL provide a public product detail page for each grid-visible article with an image gallery and full public information.

#### Scenario: View product detail
- **WHEN** a visitor opens a product card
- **THEN** a detail page shows the image gallery (all `product_images` in order), name, description, category tags, unit, public attributes, price display, availability badge, and the quantity + add-to-quote control

### Requirement: Public Price Display
The system SHALL display an article's `list_price` in the team default currency when set, and "Price on request" when not set, and SHALL never expose supplier costs or margins publicly.

#### Scenario: Price set
- **WHEN** an article has a `list_price`
- **THEN** the grid card and detail page show it formatted with the team default currency

#### Scenario: Price not set
- **WHEN** `list_price` is null
- **THEN** "Price on request" is shown instead of a number

#### Scenario: No cost leakage
- **WHEN** any public page renders
- **THEN** supplier identities, `last_quoted_price`, margins, and article codes are absent from markup and responses

### Requirement: Public Availability Display
The system SHALL show a derived three-state availability badge — "In stock", "Out of stock", or "On request" — computed from active supplier links via an aggregated query (never per-card lookups), without exposing quantities or suppliers.

#### Scenario: In stock
- **WHEN** at least one `supplier_articles` row for the article has `available_quantity > 0`, `is_active = true`, and the linked company still has `is_supplier = true`
- **THEN** the article displays an "In stock" badge

#### Scenario: Out of stock
- **WHEN** at least one qualifying supplier link has a quantity recorded and none is positive
- **THEN** the article displays an "Out of stock" badge
- **AND** the article can still be added to the quote cart

#### Scenario: Availability unknown
- **WHEN** all qualifying supplier links have null `available_quantity` (or the article has no qualifying links)
- **THEN** the article displays an "On request" badge — unknown is not presented as out of stock

### Requirement: Quote Cart
The system SHALL let visitors collect articles with chosen quantities into a session-backed quote cart and require an authenticated customer portal user to submit it.

#### Scenario: Guest builds a cart
- **WHEN** a guest sets a quantity and adds an article
- **THEN** the cart (stored in the session) reflects the line and a cart summary is reachable from every catalog page

#### Scenario: Guest attempts to submit
- **WHEN** a guest proceeds to request a quote
- **THEN** they are prompted to sign in to the customer portal or register
- **AND** the cart is preserved across the sign-in redirect within the session

#### Scenario: Signed-in user submits
- **WHEN** an active portal user submits the cart
- **THEN** a Request is created per the Catalog Quote Cart Submission requirement (customer-portal spec)
- **AND** the cart is cleared and a confirmation with the request number is shown

