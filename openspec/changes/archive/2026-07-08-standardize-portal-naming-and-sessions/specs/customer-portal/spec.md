## REMOVED Requirements

### Requirement: Customer Portal Panel
**Reason**: Capability renamed `customer-portal` → `buyer-portal` for terminology consistency; requirement continues as "Buyer Portal Panel" under `buyer-portal`.
**Migration**: No behavior change. Config keys `customer_path`/`customer_portal_enabled` become `buyer_path`/`buyer_portal_enabled` (defaults preserved); panel id `customer` → `buyer`.

### Requirement: Customer Portal User Access
**Reason**: Renamed to "Buyer Portal User Access" under `buyer-portal`.
**Migration**: Stored `portal = customer` values migrated to `portal = buyer` on `portal_invitations` and `company_portal_users`.

### Requirement: Customer Request Submission — Manual
**Reason**: Renamed to "Buyer Request Submission — Manual" under `buyer-portal`.
**Migration**: None; behavior unchanged.

### Requirement: Customer Request Submission — Document Upload
**Reason**: Renamed to "Buyer Request Submission — Document Upload" under `buyer-portal`.
**Migration**: None; behavior unchanged.

### Requirement: Customer Request Progress Tracking
**Reason**: Renamed to "Buyer Request Progress Tracking" under `buyer-portal`.
**Migration**: None; buyer-facing status labels unchanged.

### Requirement: Admin Visibility of Portal Requests
**Reason**: Moved unchanged to `buyer-portal`.
**Migration**: None.

### Requirement: Customer Portal Branding
**Reason**: Renamed to "Buyer Portal Branding" under `buyer-portal`.
**Migration**: None; shared portal shell rendering unchanged.

### Requirement: Buyer Self-Registration with Approval
**Reason**: Moved to `buyer-portal`; approval now writes `portal = buyer`.
**Migration**: None for new applications; existing records migrated with the terminology migration.

### Requirement: Catalog Quote Cart Submission
**Reason**: Moved unchanged to `buyer-portal`.
**Migration**: None.
