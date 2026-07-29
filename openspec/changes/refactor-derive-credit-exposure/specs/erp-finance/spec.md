## ADDED Requirements

### Requirement: Buyer Credit Exposure Reconciliation
The system SHALL be able to detect disagreement between a buyer's stored `credit_used` counter and its credit exposure as derived from `buyer_orders`, via a read-only `erp:reconcile-credit-exposure` Artisan command, without mutating either value. The command SHALL treat differences within a configurable tolerance (default `0.01`) as agreement, print the buyer's name, id, team, stored value, derived value, and signed difference for every buyer outside tolerance, and exit non-zero if any buyer drifted.

`BuyerCreditUsageHistory` rows recording each credit debit, restore, and release SHALL continue to be written on every order confirmation, cancellation, and payment-driven release, independent of whether anything still reads them to compute current exposure — the ledger remains the audit trail of record even though it is no longer the basis for the live exposure figure.

#### Scenario: Stored and derived values agree
- **WHEN** `erp:reconcile-credit-exposure` runs and every buyer's stored `credit_used` is within tolerance of its derived `credit_exposure`
- **THEN** the command reports the count of buyers checked and exits `0`

#### Scenario: A buyer's stored counter has drifted from its derived exposure
- **WHEN** `erp:reconcile-credit-exposure` runs against a buyer whose stored `credit_used` disagrees with its derived `credit_exposure` by more than the tolerance
- **THEN** the command prints a `DRIFT` line naming the buyer, its id, its team, the stored value, the derived value, and the difference
- **AND** the command exits `1`
- **AND** no column is written by the command; the check is read-only

#### Scenario: Sub-cent differences are tolerated
- **WHEN** a buyer's stored `credit_used` differs from its derived `credit_exposure` by less than the `--tolerance` option (default `0.01`)
- **THEN** the buyer is not reported as drifted

#### Scenario: Audit ledger keeps recording even though it is no longer authoritative
- **WHEN** a buyer order is confirmed and reserves credit, has credit released by a payment, or has its credit restored on cancellation
- **THEN** a `BuyerCreditUsageHistory` row is created recording the transaction type, amount, and before/after snapshots
- **AND** this happens regardless of the fact that `Company::credit_exposure` is computed directly from `buyer_orders`, not from this ledger
