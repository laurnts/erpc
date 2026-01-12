# System Admin Capability

## Purpose

Administrative panel for system-level management with role-based access control.

## Requirements

### Requirement: System Administrator Authentication
The system SHALL provide separate authentication for system administrators.

#### Scenario: Admin Login
- **WHEN** a system administrator logs in
- **THEN** they are authenticated to the admin panel

#### Scenario: Email Verification
- **WHEN** an admin account is created
- **THEN** email verification is required before access

### Requirement: Administrator Roles
The system SHALL support role-based access for administrators.

#### Scenario: Super Administrator
- **WHEN** a user has SuperAdministrator role
- **THEN** they can manage all system resources

#### Scenario: Limited Administrator
- **WHEN** a user has Administrator role
- **THEN** they have restricted access based on policies

### Requirement: CRM Data Management
The system SHALL allow administrators to manage all CRM data across teams.

#### Scenario: View All Companies
- **WHEN** an admin views companies
- **THEN** they see companies from all teams (no team scope)

#### Scenario: View All Users
- **WHEN** an admin views users
- **THEN** they see all users and their team memberships

#### Scenario: View All Teams
- **WHEN** an admin views teams
- **THEN** they see all teams with member counts

### Requirement: Analytics Dashboard
The system SHALL provide analytics widgets for administrators.

#### Scenario: Business Overview
- **WHEN** an admin views the dashboard
- **THEN** they see BusinessOverviewWidget with high-level metrics

#### Scenario: Sales Analytics
- **WHEN** an admin views sales analytics
- **THEN** they see SalesAnalyticsChartWidget with charts

#### Scenario: Team Performance
- **WHEN** an admin views team performance
- **THEN** they see TeamPerformanceTableWidget with statistics

### Requirement: Module Isolation
The system SHALL maintain isolation between main app and system admin module.

#### Scenario: No Main App Dependency
- **WHEN** SystemAdmin module is loaded
- **THEN** main App namespace does not depend on SystemAdmin

#### Scenario: Limited Reverse Dependency
- **WHEN** SystemAdmin needs main app classes
- **THEN** it only depends on App\Models and App\Enums

### Requirement: System Audit Log (via Spatie Activity Log)
The system SHALL maintain a comprehensive audit trail of all user actions using `spatie/laravel-activitylog`.

#### Scenario: CRUD Operation Logging
- **WHEN** a user creates, updates, or deletes any ERP entity
- **THEN** the action is logged with old/new values, user identity, and timestamp
- **AND** the `LogsActivity` trait is applied to all ERP models

#### Scenario: Auth Event Logging
- **WHEN** a user logs in or logs out
- **THEN** the event is logged with IP address, user agent, and timestamp

#### Scenario: Audit Log Admin View
- **WHEN** an admin views the system audit log
- **THEN** they see a paginated list of all system activities
- **AND** can filter by date range, user, entity type, action type
- **AND** can search by entity name, user name, or description

#### Scenario: Audit Detail View
- **WHEN** an admin clicks on an audit log entry
- **THEN** they see a modal with old and new values displayed side-by-side
- **AND** changed fields are highlighted

#### Scenario: Audit Log Export
- **WHEN** an admin exports the audit log
- **THEN** a CSV file is generated with filtered results for compliance reports

#### Scenario: Field-Level Change Tracking
- **WHEN** a model with `LogsActivity` is updated
- **THEN** only changed fields are logged (logOnlyDirty)
- **AND** empty changes are not submitted (dontSubmitEmptyLogs)

