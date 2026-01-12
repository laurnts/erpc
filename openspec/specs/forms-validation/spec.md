# Forms & Validation Capability

## Purpose

Comprehensive form handling and validation patterns using Filament and Livewire.

## Requirements

### Requirement: Filament Form Building
The system SHALL provide declarative form schema building for Filament resources.

#### Scenario: Static Form Schema
- **WHEN** a resource defines a form() method
- **THEN** it returns a Schema with component definitions

#### Scenario: Dynamic Field Exclusion
- **WHEN** a form is embedded in a RelationManager
- **THEN** context-irrelevant fields can be excluded via $excludeFields parameter

#### Scenario: Custom Fields Integration
- **WHEN** an entity supports custom fields
- **THEN** CustomFields::form()->build() adds dynamic fields to the schema

### Requirement: Form Field Validation
The system SHALL validate form inputs using Filament's validation chain.

#### Scenario: Required Field Validation
- **WHEN** a field has ->required()
- **THEN** form submission fails if the field is empty

#### Scenario: String Length Validation
- **WHEN** a field has ->maxLength(255)
- **THEN** input exceeding 255 characters is rejected

#### Scenario: Email Format Validation
- **WHEN** a field has ->email()
- **THEN** invalid email formats are rejected

#### Scenario: Unique Constraint Validation
- **WHEN** an email field must be unique
- **THEN** Rule::unique() ignores the current record on updates

### Requirement: Relationship Fields
The system SHALL support relationship-based select fields.

#### Scenario: BelongsTo Relationship
- **WHEN** a Select uses ->relationship('company', 'name')
- **THEN** options are loaded from the related model

#### Scenario: Multiple Relationships
- **WHEN** a Select has ->multiple()
- **THEN** many-to-many relationships are synced via pivot tables

#### Scenario: Searchable Relations
- **WHEN** a Select has ->searchable()->preload()
- **THEN** options are searchable and initial items are preloaded

### Requirement: Livewire Form Components
The system SHALL support Livewire-based interactive forms.

#### Scenario: Form State Management
- **WHEN** a Livewire component mounts
- **THEN** form is filled with existing data via $this->form->fill()

#### Scenario: Rate Limiting
- **WHEN** a form action is submitted
- **THEN** rate limiting prevents abuse via WithRateLimiting trait

#### Scenario: Form Submission
- **WHEN** user submits a form
- **THEN** $this->form->getState() retrieves validated data

### Requirement: Inline Record Creation
The system SHALL support creating related records inline.

#### Scenario: Suffix Action
- **WHEN** a Select field has ->suffixAction(Action::make('Create'))
- **THEN** user can create related record without leaving the form

