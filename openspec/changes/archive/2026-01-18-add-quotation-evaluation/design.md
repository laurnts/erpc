# Design: Quotation Evaluation Feature

## Context
The procurement team needs to generate internal Quotation Evaluation (QE) documents to compare supplier quotes and document the approval workflow. This document includes supplier terms, item comparison, and requires sign-off from multiple stakeholders.

## Goals / Non-Goals
**Goals:**
- Create QE documents from Compare Supplier Quotes view
- Auto-generate QE numbers with specific format
- Maintain Key Accounts as reusable master data
- Support approval workflow with multiple signatories
- Snapshot all comparison data at time of QE creation
- Provide full view page for QE documents
- Redirect to view page after creation

**Non-Goals:**
- PDF export (future enhancement)
- Digital signature capture (future enhancement)
- Email notifications to approvers (future enhancement)
- Approval status tracking (future enhancement)

## Decisions

### Decision: Full Filament Resource for QE
**What:** Create QuotationEvaluationResource with List and View pages.

**Why:** 
- QE documents need to be viewable independently
- Users need to browse/search past QE documents
- View page provides proper layout for complex data

### Decision: Key Accounts as Master Data
**What:** Create a separate `key_accounts` table for personnel involved in approval workflows.

**Why:** 
- Same people sign multiple QE documents
- Avoid re-entering contact information
- Enables consistent formatting and data quality

### Decision: Store All Data as JSON Snapshot
**What:** Store item comparison and supplier data as JSON in a single `data` column.

**Why:** 
- QE is a point-in-time document
- Supplier terms and prices may change after QE creation
- Document should reflect state at time of evaluation
- Simpler than multiple snapshot tables

### Decision: QE Number Generation
**What:** Format `{increment}-DS/QE/{roman_month}/{year}` with yearly reset per team.

**Implementation:**
```php
private const ROMAN_MONTHS = [
    1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV',
    5 => 'V', 6 => 'VI', 7 => 'VII', 8 => 'VIII',
    9 => 'IX', 10 => 'X', 11 => 'XI', 12 => 'XII'
];

public static function generateQeNumber(int $teamId): string
{
    $year = now()->year;
    $month = now()->month;
    
    $lastQe = self::where('team_id', $teamId)
        ->whereYear('created_at', $year)
        ->orderByDesc('id')
        ->first();

    $increment = 1;
    if ($lastQe) {
        preg_match('/^(\d+)-/', $lastQe->qe_number, $matches);
        $increment = ((int) ($matches[1] ?? 0)) + 1;
    }
    
    return sprintf('%03d-DS/QE/%s/%d', $increment, self::ROMAN_MONTHS[$month], $year);
}
```

## Database Schema

### Key Accounts Table
```sql
CREATE TABLE key_accounts (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    INDEX idx_team (team_id)
);
```

### Quotation Evaluations Table
```sql
CREATE TABLE quotation_evaluations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    team_id BIGINT NOT NULL REFERENCES teams(id) ON DELETE CASCADE,
    request_id BIGINT NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    qe_number VARCHAR(50) NOT NULL,
    description TEXT,
    qe_date DATE NOT NULL,
    
    -- Central Purchasing (FK to key_accounts)
    prepared_by_id BIGINT REFERENCES key_accounts(id) ON DELETE SET NULL,
    dept_head_sales_id BIGINT REFERENCES key_accounts(id) ON DELETE SET NULL,
    deputy_director_id BIGINT REFERENCES key_accounts(id) ON DELETE SET NULL,
    approved_by_id BIGINT REFERENCES key_accounts(id) ON DELETE SET NULL,
    
    -- Snapshot data (items, suppliers, totals)
    data JSON NOT NULL,
    
    created_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    UNIQUE KEY uk_qe_number_team (team_id, qe_number),
    INDEX idx_request (request_id)
);
```

### JSON Data Structure
```json
{
  "request": {
    "id": 42,
    "request_number": "REQ-2026-0042",
    "title": "Motor Procurement Project"
  },
  "items": [
    {
      "id": 1,
      "description": "Industrial Motor 5HP",
      "quantity": 2,
      "unit": "pcs",
      "prices": {
        "10": {
          "supplier_id": 10,
          "unit_price": 4800,
          "line_subtotal": 9600,
          "line_tax": 1056,
          "line_total": 10656,
          "is_best_price": true
        },
        "15": {
          "supplier_id": 15,
          "unit_price": 5200,
          "line_subtotal": 10400,
          "line_tax": 1144,
          "line_total": 11544,
          "is_best_price": false
        }
      }
    }
  ],
  "suppliers": [
    {
      "id": 10,
      "name": "MotorCorp",
      "currency_code": "USD",
      "delivery_type": "Franco",
      "delivery_type_details": "Jakarta area only",
      "is_taxable": true,
      "delivery_term": "14 days from PO",
      "payment_terms_days": 30,
      "subtotal": 9600,
      "tax_total": 1056,
      "grand_total": 10656
    },
    {
      "id": 15,
      "name": "PumpWorld",
      "currency_code": "USD",
      "delivery_type": "Loco",
      "delivery_type_details": null,
      "is_taxable": false,
      "delivery_term": "7 days",
      "payment_terms_days": 14,
      "subtotal": 10400,
      "tax_total": 1144,
      "grand_total": 11544
    }
  ]
}
```

## Component Structure

### QuotationEvaluationResource (Filament)
```
QuotationEvaluationResource
├── Pages
│   ├── ListQuotationEvaluations
│   ├── ViewQuotationEvaluation (custom infolist)
│   └── EditQuotationEvaluation (Central Purchasing only)
├── table()
│   └── Columns: qe_number, request.request_number, description, qe_date, creator.name
└── infolist()
    ├── QE Information Section
    ├── Item Comparison Section (custom view component)
    ├── Supplier Information Section
    └── Central Purchasing Section
```

### QuotationEvaluationForm (Livewire)
```
QuotationEvaluationForm
├── Properties
│   ├── Request $request
│   ├── string $description
│   ├── ?int $preparedById
│   ├── ?int $deptHeadSalesId
│   ├── ?int $deputyDirectorId
│   └── ?int $approvedById
├── Methods
│   ├── mount(Request $request)
│   ├── save() → redirect to view page
│   ├── createKeyAccount(array $data): int
│   └── buildSnapshotData(): array
└── View
    ├── QE Information Section
    └── Central Purchasing Section
```

## Risks / Trade-offs
- **Large JSON storage**: Complex comparison data stored as JSON → Acceptable for document-style storage
- **No real-time updates**: Snapshot doesn't update if quotes change → Intentional for document integrity

## Migration Plan
1. Create `key_accounts` migration and model
2. Create KeyAccountResource
3. Create `quotation_evaluations` migration and model
4. Create QuotationEvaluationResource with List/View pages
5. Create QuotationEvaluationForm Livewire component
6. Integrate "Create QE" button into Compare Supplier Quotes
7. Implement redirect to view page after save

## Open Questions
- Should QE be editable after creation? (Currently: Only Central Purchasing fields)
- Should there be QE versioning? (Future enhancement)
