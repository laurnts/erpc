## ADDED Requirements

### Requirement: Document Number Allocation
Shipment (`shipment_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). This requirement covers `shipment_number` only; the separate Delivery Order PDF number (`Shipment::do_number`, see the Delivery Order PDF Generation requirement) is not part of this allocator and continues to be derived by reading existing records.

#### Scenario: Concurrent shipment creates do not collide
- **WHEN** two shipments are created for the same team in the same year at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `shipment_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: Sequence does not regress past 9999
- **WHEN** a team's shipment count for a year is already at 9999
- **THEN** the next allocated `shipment_number` sequence value is 10000, not a value already issued

#### Scenario: Delivery Order numbering is unaffected
- **WHEN** a Delivery Order PDF number (`do_number`) is generated for a shipment
- **THEN** it continues to be derived from existing `do_number` records exactly as before this change, independent of the `shipment_number` counter
