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
The system SHALL support creating related records inline with consistent form schemas.

#### Scenario: Suffix Action
- **WHEN** a Select field has ->suffixAction(Action::make('Create'))
- **THEN** user can create related record without leaving the form

#### Scenario: Reusable Form Schema
- **WHEN** a resource defines a static getFormSchema() method
- **THEN** both the main form and inline create modals use the same schema
- **AND** form consistency is maintained across all entry points
- **AND** CustomFields are included in getFormSchema(), not just form()

#### Scenario: Inline Create with Shared Schema
- **WHEN** a Select uses ->createOptionForm(OtherResource::getFormSchema())
- **THEN** the inline create modal shows the same fields as the main create form
- **AND** createOptionUsing() handles entity creation with team scoping

#### Scenario: Nested Modal Creation
- **WHEN** creating a Person, user clicks "+" on Companies field
- **THEN** an inline Company create form opens as a nested modal
- **AND** the Company form includes its full getFormSchema() fields

#### Scenario: Circular Reference Prevention
- **WHEN** Resource A can inline create Resource B AND Resource B can inline create Resource A
- **THEN** exclusion parameters (e.g., excludeCompaniesField) prevent infinite modal nesting
- **AND** the excluded field is hidden only in the inline context, not the main form

### Requirement: CRM Entity Inline Forms
The system SHALL maintain form consistency across CRM entity relationships.

#### Scenario: Companies → People Inline Create
- **WHEN** user creates a Company and clicks (+) on People field
- **THEN** inline People form shows: Name, CustomFields (Emails, Phone, Job Title, LinkedIn)
- **AND** Companies field is excluded (circular reference)
- **AND** created Person is linked to the new Company

#### Scenario: People → Companies Inline Create
- **WHEN** user creates a Person and clicks (+) on Companies field
- **THEN** inline Company form shows: all Company fields + CustomFields
- **AND** People field is excluded (circular reference)
- **AND** Code field is excluded (auto-generated in main form only)

#### Scenario: Buyers → People Inline Create
- **WHEN** user creates a Buyer and clicks (+) on People/Contacts field
- **THEN** inline People form matches People → Create Person form
- **AND** Companies field is excluded (Buyer already provides company context)

#### Scenario: Suppliers → People Inline Create
- **WHEN** user creates a Supplier and clicks (+) on People/Contacts field
- **THEN** inline People form matches People → Create Person form
- **AND** Companies field is excluded (Supplier already provides company context)

### Requirement: ERP Entity Inline Forms
The system SHALL maintain form consistency across ERP entity relationships.

#### Scenario: Projects → Buyers Inline Create
- **WHEN** user creates a Project and clicks (+) on Associated Buyer field
- **THEN** inline Buyer form matches Buyer → Create Buyer form
- **AND** People field is excluded (to simplify inline create)
- **AND** created Buyer is linked to the Project

#### Scenario: Requests → Buyers Inline Create
- **WHEN** user creates a Request and clicks (+) on Buyer field
- **THEN** inline Buyer form matches Buyer → Create Buyer form
- **AND** People field is excluded (to simplify inline create)
- **AND** created Buyer is set as the Request's buyer

#### Scenario: Requests → Projects Inline Create
- **WHEN** user creates a Request and clicks (+) on Project field
- **THEN** inline Project form matches Project → Create Project form
- **AND** all Project fields including Schedule, Status, Notes are shown
- **AND** created Project is linked to the Request

### Requirement: Tags/Categories Inline Forms
The system SHALL support consistent Tag/Category inline creates across multiple resources.

#### Scenario: Any Entity → Tags Inline Create
- **WHEN** user clicks (+) on Categories/Tags field from Company, Buyer, Supplier, or Article
- **THEN** inline Tag form shows: Category Name, Color, Description
- **AND** uses `TagResource::getFormSchema()` for consistency
- **AND** created Tag is linked to the entity via taggables pivot

### Requirement: Inline Form Field Consistency
The system SHALL ensure inline forms match main forms exactly (with allowed exceptions).

#### Scenario: CustomFields in Inline Forms
- **WHEN** an entity has CustomFields configured
- **THEN** CustomFields appear in both main form AND inline create modals
- **AND** CustomFields::form()->build() is called within getFormSchema()

#### Scenario: Auto-Generated Fields Exclusion
- **WHEN** a field is auto-generated (e.g., code)
- **THEN** it appears only in the main form, not inline creates
- **AND** getFormSchema() does not include auto-generated fields

#### Scenario: Team Scoping in Inline Creates
- **WHEN** a record is created via inline (+) button
- **THEN** team_id is set to auth()->user()->currentTeam->id
- **AND** creator_id is set to auth()->id()

