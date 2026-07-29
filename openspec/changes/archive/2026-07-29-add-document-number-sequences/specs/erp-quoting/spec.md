## ADDED Requirements

### Requirement: Document Number Allocation
Buyer quote (`quote_number`), supplier quote (`quote_number`), Quotation Evaluation (`qe_number`), and Profit and Loss (`pnl_number`) numbers SHALL be allocated from a locked counter row (one row per team, document key, and calendar year in `document_number_sequences`) rather than by reading the highest existing number, so that concurrent creates cannot receive the same number and the sequence does not regress once a team's yearly count passes 9999 (a string-sorted "highest number" query would otherwise rank `'9999'` above `'10000'`). Creating a new buyer quote version (`BuyerQuote::createNewVersion()`) allocates a fresh sequence value the same way a first-version quote does. Numbers are strictly monotonic per (team, key, year): a rolled-back or deleted document permanently skips its number rather than having that number reissued to a later document.

For Quotation Evaluation and Profit and Loss specifically, the counter also fixes a prior defect where the next number was derived from the last **inserted** database row (ordered by `id`) rather than from the highest number actually issued: an out-of-order insert (for example a re-saved or backfilled record) could previously reset the count and reissue a number already in use. The counter is immune to insert order because it never inspects existing rows.

#### Scenario: Concurrent quote creates do not collide
- **WHEN** two supplier quotes are created for the same team in the same year at effectively the same time
- **THEN** each receives a distinct, correctly incrementing `quote_number`
- **AND** neither create fails due to a duplicate-number save

#### Scenario: QE and PNL numbering survives out-of-order inserts
- **WHEN** Quotation Evaluation or Profit and Loss records are saved in an order that does not match the order their numbers were originally issued in
- **THEN** the next allocated number is still one greater than the highest number ever issued for that team and year
- **AND** no previously issued QE or PNL number is reissued

#### Scenario: 30 rapid allocations never collide
- **WHEN** 30 QE numbers or 30 PNL numbers are allocated in immediate succession for the same team
- **THEN** all 30 numbers in each set are distinct
