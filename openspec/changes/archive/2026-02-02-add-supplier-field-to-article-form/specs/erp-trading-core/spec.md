# erp-trading-core Specification Delta

## MODIFIED Requirements

### Requirement: Article-Supplier Relationship
The system SHALL manage a many-to-many relationship between Articles and Suppliers via the supplier_articles pivot table.

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
- **AND** only one supplier per article can be preferred

#### Scenario: Track supplier pricing history
- **WHEN** a supplier quote includes an article
- **THEN** last_quoted_price, last_quoted_currency_id, and last_quoted_at are updated
- **AND** previous pricing is preserved in quote history
