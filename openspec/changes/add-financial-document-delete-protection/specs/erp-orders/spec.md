# erp-orders Delta

## ADDED Requirements

### Requirement: Buyer Order Delete Protection
The system SHALL enforce database-level `RESTRICT` (not `CASCADE`) on `buyer_orders.request_id` →
`requests` and `buyer_orders.buyer_id` → `companies`. A request or buyer company that has a buyer
order SHALL NOT be hard-deletable; it must be archived (soft-deleted) instead. The `buyer_orders.team_id`
foreign key is unaffected and remains `cascadeOnDelete()`.

#### Scenario: Force-deleting a request with a buyer order is rejected
- **WHEN** a request that has a `BuyerOrder` is force-deleted
- **THEN** the database raises a foreign key violation and the request row is not removed

#### Scenario: Force-deleting a buyer company with a buyer order is rejected
- **WHEN** a buyer company that has a `BuyerOrder` is force-deleted
- **THEN** the database raises a foreign key violation and the company row is not removed
