# erp-quoting Specification Delta

## ADDED Requirements

### Requirement: Quotation Evaluation Item Scope
The system SHALL make Quotation Evaluation available when a request has at least one goods item, and SHALL limit the evaluation's contents to goods items.

#### Scenario: QE on a mixed request covers goods items only
- **WHEN** an admin creates a Quotation Evaluation for a request with goods and services items
- **THEN** the evaluation lists supplier quote lines for goods items only
- **AND** services items are excluded from the comparison

#### Scenario: QE unavailable for service-only requests
- **WHEN** all of a request's items are services items
- **THEN** Quotation Evaluation creation is blocked with a notice that it applies to goods items only

---

### Requirement: Item-Type-Driven Quote Composition
The system SHALL derive quote structure per item from the item's type: services items carry their child-item breakdown into quotes, and job-progress payment terms are available when a quote's request has at least one services item.

#### Scenario: Supplier quote generation for a mixed request
- **WHEN** supplier quotes are generated for a request with a goods item and a services main item having two child items
- **THEN** the goods item produces one flat quote line
- **AND** the services main item produces a quote line with its two child lines nested beneath it
- **AND** child lines are excluded from quote totals

#### Scenario: Job progress on payment terms
- **WHEN** an admin edits payment terms on a quote whose request has at least one services item
- **THEN** the Job Progress (%) field is available on each payment-term row
- **AND** the field is absent when the request has only goods items

#### Scenario: Totals on mixed documents
- **WHEN** totals are computed for a quote covering goods lines and a services main line with children
- **THEN** goods lines and the services main line are summed
- **AND** child lines are always excluded from totals
