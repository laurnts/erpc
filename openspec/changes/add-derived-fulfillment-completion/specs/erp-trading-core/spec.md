## MODIFIED Requirements

### Requirement: Item-Level Fulfillment Channels
The system SHALL fulfill each item through the channel of its type — goods items via shipments, services main items via acceptance reports — and SHALL restrict each fulfillment document to items of its channel.

#### Scenario: Mixed request exposes both channels
- **WHEN** a request has one goods item and one services main item
- **THEN** shipments are available for the goods item
- **AND** acceptance reports are available for the services main item

#### Scenario: Service child items follow their parent
- **WHEN** a services main item is covered by an acceptance report
- **THEN** its child items are considered covered with it
- **AND** child items are never individually selectable on fulfillment documents

## ADDED Requirements

### Requirement: Derived Fulfillment Completion
The system SHALL derive per-channel fulfillment completion on a request — the goods channel is complete when every goods main item is fully covered by **delivered** shipment quantities (pending, in-transit, and failed shipments do not count), the services channel is complete when every services main item is covered by an acceptance report, and a channel with no items is complete — and SHALL derive the request as fulfilled when all channels are complete. Stage progression remains manual, but the transition to the completed stage MUST require derived fulfillment.

#### Scenario: Mixed request fulfilled when both channels complete
- **WHEN** a request has goods items fully shipped and every services main item covered by an acceptance report
- **THEN** the request's derived fulfillment status is "fulfilled"

#### Scenario: Partially shipped goods block fulfillment
- **WHEN** a goods main item with quantity 10 has shipments covering only 6
- **THEN** the goods channel is incomplete
- **AND** the request's derived fulfillment status is not "fulfilled"

#### Scenario: Undelivered shipments do not count as coverage
- **WHEN** a goods main item is fully covered by shipment documents that are still pending or in transit
- **THEN** the goods channel is incomplete until those shipments are delivered

#### Scenario: Single-type request derives from its only channel
- **WHEN** a services-only request has every services main item covered by an acceptance report
- **THEN** the request's derived fulfillment status is "fulfilled" (the empty goods channel is vacuously complete)

#### Scenario: Completed stage gated on fulfillment
- **WHEN** a user attempts to move a request to the completed stage while its derived fulfillment status is not "fulfilled"
- **THEN** the transition is rejected with a message identifying the incomplete channel(s)

#### Scenario: Fulfillment status displayed
- **WHEN** a user views a request (view page or list)
- **THEN** the derived per-channel and overall fulfillment status is visible without opening fulfillment documents
