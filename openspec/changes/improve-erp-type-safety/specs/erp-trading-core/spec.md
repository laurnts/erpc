## ADDED Requirements

### Requirement: Safe Type Casting Utility
The system SHALL provide a utility class for validated type casting operations.

#### Scenario: Cast to float with validation
- **WHEN** calling `SafeCast::toFloat($value)`
- **AND** value is numeric
- **THEN** returns float representation
- **AND** if value is null or empty string, returns default (0.0)
- **AND** if value is non-numeric, returns default

#### Scenario: Cast to int with validation
- **WHEN** calling `SafeCast::toInt($value)`
- **AND** value is numeric
- **THEN** returns integer representation
- **AND** if value is null or empty string, returns default (0)
- **AND** if value is non-numeric, returns default

#### Scenario: Cast to string with validation
- **WHEN** calling `SafeCast::toString($value)`
- **AND** value is scalar
- **THEN** returns string representation
- **AND** if value is null, returns default ('')
- **AND** if value is array or object, returns default

---

### Requirement: PHPDoc Generic Annotations
The system SHALL use PHPDoc generics for Collection return types.

#### Scenario: Computed property with generic type
- **WHEN** Livewire component has computed property returning Collection
- **THEN** PHPDoc includes `@return Collection<int, ModelClass>`
- **AND** IDE provides autocomplete for collection items

#### Scenario: Query result with generic type
- **WHEN** variable holds Eloquent query result
- **THEN** PHPDoc annotation documents generic type
- **AND** loop variables have proper type inference

---

### Requirement: Array Shape Documentation
The system SHALL document complex array structures with PHPDoc array shapes.

#### Scenario: Snapshot data structure
- **WHEN** `QuotationEvaluation::buildSnapshotData()` returns array
- **THEN** PHPDoc documents full array shape
- **AND** includes nested array structures for items and suppliers
- **AND** PHPStan can validate array key access

#### Scenario: JSON data column access
- **WHEN** accessing `data` JSON column properties
- **THEN** typed getter methods provide safe access
- **AND** return types document expected structure
- **AND** null/missing keys return safe defaults

---

### Requirement: Typed Closure Parameters
The system SHALL type all closure parameters in functional operations.

#### Scenario: Collection map with typed closure
- **WHEN** using `$collection->map(fn ($item) => ...)`
- **THEN** closure parameter has explicit type: `fn (ModelClass $item): ReturnType =>`
- **AND** PHPStan validates closure body against type

#### Scenario: Collection filter with typed closure
- **WHEN** using `$collection->filter(fn ($item) => ...)`
- **THEN** closure parameter has explicit type
- **AND** return type is `bool`

---

### Requirement: Safe Numeric Operations
The system SHALL validate numeric values before calculations.

#### Scenario: Calculate line totals
- **WHEN** calculating item totals from form state
- **THEN** `SafeCast::toFloat()` validates input values
- **AND** invalid inputs default to zero
- **AND** no silent type coercion errors

#### Scenario: Format currency values
- **WHEN** formatting values with `number_format()`
- **THEN** input is validated as numeric first
- **AND** non-numeric values use safe default
