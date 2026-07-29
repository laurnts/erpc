## ADDED Requirements

### Requirement: Document Number Allocation
Buyer order (`order_number`) and supplier order (`po_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Only the base PO number for a request consumes the counter; the `-A`/`-B`/`-C` suffix issued to the second and later supplier orders on the same request reuses the first order's base number and does not allocate a new sequence value.

#### Scenario: Concurrent order creates do not collide
- **WHEN** two buyer orders are created for the same team in the same year at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `order_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's supplier order count for a year is already at 9999
- **THEN** the next allocated base `po_number` sequence value is 10000, not a value already issued

#### Scenario: Suffixed supplier orders on the same request do not consume additional sequence values
- **WHEN** a second and third supplier order are created for a request that already has a supplier order `PO-2026-0042`
- **THEN** they receive `PO-2026-0042-A` and `PO-2026-0042-B`
- **AND** the team's `supplier_order` counter for 2026 is unaffected by these two additional orders
