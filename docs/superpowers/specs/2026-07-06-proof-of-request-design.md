# Proof of Request — Design

Date: 2026-07-06
Status: Approved (Approach A, lean)

## Problem

Every request must have a proof of the buyer's actual request. Buyers on the portal
already can't submit an empty request: a `MANUAL` submission requires typed line items
(`Repeater` with `minItems(1)`), and a `DOCUMENT` submission requires at least one uploaded
file (`FileUpload` `->required()`, stored in the `attachments` media collection). `CATALOG`
submissions come from the catalog cart with real items.

The unguarded path is **staff-created requests**. The staff create form
(`RequestResource::getFormSchema`) has no file-upload field and sets no `submission_method`
(`null` → rendered as "Staff Entry"). So a staff member who received a request by email or
letter can create it with no attached evidence. Separately, the staff `RequestResource`
(list/view/create) never surfaces the `attachments` collection at all, so staff cannot even
open a buyer's uploaded document from the request page.

## Requirement

- Every request must carry proof.
  - Buyer `MANUAL` / `CATALOG` → the buyer's own authenticated entry is the proof (no file).
  - Buyer `DOCUMENT` → the uploaded file(s) are the proof (already enforced).
  - **Staff entry → must upload at least one proof document** (the buyer's email/letter/PDF).
- Pre-production: **new staff requests only.** No existing data to protect, so there is no
  backfill, no "missing proof" nudge, and no hard block on editing/advancing existing requests.

## Approach A (chosen): reuse `attachments` + a model helper

Staff proof uploads land in the **existing `attachments` media collection** that buyer
`DOCUMENT` uploads already use. One collection, one concept, no migration. Rejected
alternatives: a dedicated `proof_of_request` collection (would force rerouting the working
buyer `DOCUMENT` flow for a distinction nobody needs) and a `proof_type` column (redundant —
media presence plus submission method already answer "is there proof").

The `submission_method` enum is **not** changed; `null` continues to mean "Staff Entry".
Adding a real `STAFF` case would ripple through `isPortalSubmission()`, the Source label, and
the list filter for no functional gain.

## Changes (4 touch-points)

### 1. Model — `app/Models/Request.php`

- Add constant:
  ```php
  public const string PROOF_UPLOAD_DIRECTORY = 'uploads-tmp/request-proof';
  ```
  (Temp staging directory for the staff `FileUpload`; the collection is still `attachments`.)
- Add method naming the invariant:
  ```php
  public function hasProofOfRequest(): bool
  {
      if (in_array($this->submission_method, [
          RequestSubmissionMethod::MANUAL,
          RequestSubmissionMethod::CATALOG,
      ], true)) {
          return true;
      }

      return $this->getMedia('attachments')->isNotEmpty();
  }
  ```
  Note: a buyer `DOCUMENT` request also passes via the media branch. A staff request
  (`submission_method === null`) passes only when it has at least one `attachments` file.

The `attachments` collection is already registered in `registerMediaCollections()` — no change
there.

### 2. Staff create form + handler

**`RequestResource::getFormSchema`** — add a required `FileUpload` **only when `$isCreate`**
(edit form untouched), in a "Proof of Request" `Section`, mirroring the buyer upload's rules:

```php
FileUpload::make('proof_files')
    ->label('Proof of Request')
    ->helperText("Buyer's email, letter, RFQ, or PO. PDF, Excel, Word, or images (max 10MB per file).")
    ->acceptedFileTypes([
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'image/png', 'image/jpeg', 'image/jpg',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ])
    ->disk('local')
    ->directory(Request::PROOF_UPLOAD_DIRECTORY)
    ->visibility('private')
    ->multiple()
    ->maxFiles(10)
    ->maxSize(10240)
    ->required()
```

**`CreateRequest::handleRecordCreation`** — override to strip `proof_files` from the model
data, create the record, then attach via the existing action (same pattern as
`CreateCustomerRequest`):

```php
protected function handleRecordCreation(array $data): Model
{
    $files = $this->form->getState()['proof_files'] ?? [];
    unset($data['proof_files']);

    /** @var \App\Models\Request $record */
    $record = parent::handleRecordCreation($data);

    app(AttachUploadedFiles::class)->execute(
        $record, $files, 'attachments', Request::PROOF_UPLOAD_DIRECTORY,
    );

    return $record;
}
```

`submission_method` stays `null` (Staff Entry). No `submitted_at` / `submitted_by` set.

### 3. Staff view — `ViewRequest` infolist

Add a collapsible "Proof of Request" `Section` that lists every `attachments` media item with a
private download link — surfacing both buyer uploads and staff proof. View/download only, no
add/remove after creation. Reuse the display pattern from
`ViewCustomerRequest`'s `attachments_list` entry (build an `HtmlString` of links from
`$record->getMedia('attachments')`, using a temporary/private URL). Hidden when the collection
is empty.

### 4. Tests (`tests/Feature`)

- New file `RequestProofOfRequestTest.php`:
  - Staff create is **rejected** (form error on `proof_files`) when no file is provided.
  - Staff create **succeeds** with a file and the media lands in `attachments`.
  - `hasProofOfRequest()` truth table: `MANUAL` → true, `CATALOG` → true, staff (`null`) with no
    media → false, staff with an `attachments` file → true, buyer `DOCUMENT` with a file → true.
- Extend `RequestResourceViewTest.php`: the staff view renders the "Proof of Request" section
  with a working download link when the request has an `attachments` file, and hides it when
  there are none.

## Out of scope

- Backfilling / enforcing proof on existing requests.
- Editing or removing proof after creation.
- Any header indicator (e.g. paperclip/count next to Source).
- Any change to the buyer portal forms or the `RequestSubmissionMethod` enum.

## Testing strategy

Pest feature tests (Livewire) for form validation, media attachment, and view rendering; a
unit-style assertion set for `hasProofOfRequest()`. Run with
`php artisan test --compact --filter=Proof` plus the existing `RequestResourceViewTest`.
