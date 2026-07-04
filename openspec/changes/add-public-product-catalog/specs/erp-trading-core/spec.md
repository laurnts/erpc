# erp-trading-core Delta

## MODIFIED Requirements

### Requirement: Articles Entity
The system SHALL manage articles (products/services) with flexible attributes stored as JSONB, multiple product images, an optional human-published public list price, and a flag controlling visibility in the public product grid.

#### Scenario: Create an article
- **WHEN** an admin creates an article "Industrial Motor 5HP" with unit "pcs"
- **THEN** the article is created and scoped to the current team

#### Scenario: Add custom attributes
- **WHEN** an admin adds attributes {"voltage": "220V", "wattage": 1500, "certification": "CE"}
- **THEN** the attributes are stored as JSONB on the article

#### Scenario: Set default tax code on article
- **WHEN** an admin sets default_tax_code_id on an article
- **THEN** new quote/order items for this article default to that tax code

#### Scenario: Assign categories to article
- **WHEN** an admin assigns tags ["Industrial", "Motors"] to an article
- **THEN** the article is associated with those categories

#### Scenario: Link article to supplier
- **WHEN** a supplier quote includes an article
- **THEN** the supplier_articles pivot is updated with last_quoted_price and date

#### Scenario: Search articles by attributes
- **WHEN** a user searches for articles where voltage = "220V"
- **THEN** matching articles are returned using GIN index on attributes

#### Scenario: Upload multiple product images
- **WHEN** an admin uploads images to an article's Images section
- **THEN** they are stored in the `product_images` media collection (Spatie Media Library), reorderable, with thumbnail and medium conversions
- **AND** the first image is the primary image used on the public grid card

#### Scenario: Publish article to the public grid
- **WHEN** an admin enables `show_in_product_grid` on an article
- **THEN** the article becomes eligible for the public catalog (subject to `is_active`)
- **AND** the flag defaults to false for new and existing articles

#### Scenario: Publish a list price
- **WHEN** an admin saves a `list_price` (decimal 15,4, team default currency) on an article
- **THEN** `list_price_updated_at` is stamped and `price_review_needed` is cleared — saving is the publish act
- **AND** clearing the price makes the public catalog show "Price on request"

### Requirement: Article-Supplier Relationship
The system SHALL manage a many-to-many relationship between Articles and Suppliers via the supplier_articles pivot table, including supplier-owned standing price and available quantity fields that are distinct from the staff-owned quoted-price history, with at most one preferred supplier per article enforced at the database level.

#### Scenario: Assign suppliers to article via Article form
- **WHEN** an admin creates or edits an article and selects suppliers from the Suppliers field in the Article form
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** the Suppliers field appears in the Article form after the Categories field
- **AND** the field supports multiple supplier selection
- **AND** the field is searchable and preloads options
- **AND** only active suppliers from the current team are shown (filtered by is_supplier=true)

#### Scenario: Create supplier inline from Article form
- **WHEN** an admin clicks the (+) button on the Suppliers field in the Article form
- **THEN** an inline supplier creation form appears
- **AND** the form uses `SupplierResource::getFormSchema(excludePeopleField: true)` for consistency
- **AND** the created supplier is automatically selected and linked to the article
- **AND** the supplier is created with is_supplier=true and scoped to the current team

#### Scenario: Assign suppliers to article via relation manager
- **WHEN** an admin assigns suppliers to an article via the Suppliers relation manager tab
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** pivot data includes supplier_sku, last_quoted_price, lead_time_days, is_preferred
- **NOTE:** This method remains available for detailed supplier-article relationship management

#### Scenario: Assign suppliers when creating article inline from Request Item
- **WHEN** an admin creates an article inline from the Request Item form and selects suppliers
- **THEN** the article is created with the selected suppliers assigned
- **AND** suppliers are synced via `$article->suppliers()->sync($data['suppliers'])`
- **AND** the Suppliers field works correctly in the modal context

#### Scenario: Assign articles to supplier
- **WHEN** an admin assigns articles to a supplier via the Articles field
- **THEN** the associations are stored in the supplier_articles pivot table
- **AND** pivot data includes supplier_sku, last_quoted_price, lead_time_days, is_preferred

#### Scenario: Set preferred supplier for article
- **WHEN** an admin sets is_preferred = true for a supplier-article link
- **THEN** that supplier becomes the preferred supplier for sourcing
- **AND** any previously preferred supplier for the article is demoted in the same transaction (shared `SetPreferredSupplier` action)
- **AND** a partial unique index (`article_id` where `is_preferred`) enforces at-most-one preferred supplier at the database level

#### Scenario: Track supplier pricing history
- **WHEN** a supplier quote includes an article
- **THEN** last_quoted_price, last_quoted_currency_id, and last_quoted_at are updated
- **AND** previous pricing is preserved in quote history

#### Scenario: Maintain supplier standing price
- **WHEN** a supplier (via the supplier portal) or staff (via the relation managers) set `supplier_price` with its currency on a supplier-article link
- **THEN** the value is stored on the pivot with `supplier_price_updated_at` stamped
- **AND** `supplier_price` (forward-looking standing offer, supplier-owned) remains distinct from `last_quoted_price` (backward-looking RFQ history, staff-owned)

#### Scenario: Maintain available quantity
- **WHEN** a supplier or staff set `available_quantity` on a supplier-article link
- **THEN** the value and `quantity_updated_at` are stored on the pivot
- **AND** null means "unknown", which is distinct from 0

#### Scenario: Supplier-writable fields are confined
- **WHEN** a supplier updates their own supplier-article link
- **THEN** only `supplier_price`, `supplier_price_currency_id`, `available_quantity`, and `lead_time_days` are writable
- **AND** `is_preferred`, `is_active`, `supplier_sku`, `notes`, and `last_quoted_*` remain staff-only

## ADDED Requirements

### Requirement: Catalog List Pricing
The system SHALL provide an assisted price suggestion on the article form that applies the canonical `MarginConvention` and the existing `TeamErpSettings` default margin to supplier cost, and SHALL flag published prices for review when underlying supplier costs change; suggested or flagged prices SHALL never change the public price without an explicit admin save.

#### Scenario: Suggest a list price
- **WHEN** an admin triggers "Suggest price" on an article
- **THEN** the cost is resolved in order: preferred supplier's `supplier_price`, else preferred supplier's `last_quoted_price`, else the lowest converted `supplier_price` among active supplier links
- **AND** the cost is converted to the team default currency via the exchange-rate service
- **AND** the suggestion equals `MarginConvention::netUnitPrice(cost, TeamErpSettings default_margin_percent)`
- **AND** the suggestion only fills the input — the admin must save to publish

#### Scenario: Suggestion unavailable or partially convertible
- **WHEN** the resolved cost rung is a single preferred-supplier value and its exchange rate is missing
- **THEN** no suggestion is made and the notice names the missing currency
- **WHEN** the lowest-price rung is used and some candidates are unconvertible
- **THEN** those candidates are skipped and the notice lists the skipped suppliers/currencies; the action aborts only if no candidate converts

#### Scenario: Supplier price change flags review
- **WHEN** a supplier price write, preferred-supplier change, or the daily refresh command changes the best converted cost such that the published `list_price` yields a margin below the team default (or the cost becomes unconvertible)
- **THEN** `price_review_needed` is set on the article (persisted, recomputed at write time — never at public render time)
- **AND** the admin article list shows a review badge and a filter reading the persisted flag
- **AND** the public `list_price` is never changed automatically
