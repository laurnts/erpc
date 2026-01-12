# AI Summaries Capability

## Purpose

AI-powered summaries for CRM entities using Prism PHP and configurable AI providers.

## Requirements

### Requirement: Entity Summary Generation
The system SHALL generate AI-powered summaries for CRM entities.

#### Scenario: Generate Summary
- **WHEN** a user requests an AI summary for an entity
- **THEN** the system generates a summary using configured AI provider

#### Scenario: Build Context
- **WHEN** generating a summary
- **THEN** RecordContextBuilder assembles relevant entity data for the AI prompt

#### Scenario: Cache Summary
- **WHEN** a summary is generated
- **THEN** it is stored in the ai_summaries table for future reference

### Requirement: Summary Invalidation
The system SHALL invalidate AI summaries when entity data changes.

#### Scenario: Invalidate on Update
- **WHEN** an entity with a summary is updated
- **THEN** the cached summary is invalidated via InvalidatesRelatedAiSummaries trait

#### Scenario: Regenerate Summary
- **WHEN** a user requests a summary after invalidation
- **THEN** a fresh summary is generated with current data

### Requirement: AI Provider Configuration
The system SHALL support multiple AI providers through configuration.

#### Scenario: Configure Provider
- **WHEN** an admin sets the AI provider in configuration
- **THEN** all AI operations use that provider

#### Scenario: Provider Fallback
- **WHEN** the primary provider is unavailable
- **THEN** operations fail gracefully with appropriate error handling

