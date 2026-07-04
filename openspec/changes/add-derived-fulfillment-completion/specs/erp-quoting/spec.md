## ADDED Requirements

### Requirement: Goods-Only Quote Ranking on Mixed Requests
The system SHALL order and mark supplier quotes in the Quotation Evaluation by a goods-lines-only comparable subtotal, so that services pricing never influences the goods comparison ranking, and SHALL display both the goods subtotal and the full quote total per supplier on mixed requests.

#### Scenario: Mixed-request quotes ranked by goods subtotal
- **WHEN** a Quotation Evaluation is created for a mixed request where supplier A's goods lines total less than supplier B's, but A's services lines make A's overall quote total higher
- **THEN** supplier A ranks ahead of supplier B in the evaluation
- **AND** each supplier shows both its goods subtotal and its full quote total

#### Scenario: Goods-only request ranking unchanged
- **WHEN** a Quotation Evaluation is created for a request with only goods items
- **THEN** the ranking equals the existing total-based ordering
