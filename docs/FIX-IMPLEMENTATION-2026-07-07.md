# Bugfix Implementation — 2026-07-07

## 1. Module Overview

- **Document Version:** 1.0 (Laravel 12 / Filament 5)
- **Environment:** Production (`app-erpc.bpdn.id`, `erpc.bpdn.id`)
- **Core Purpose:** Record production fixes for email configuration, Mailpit fallback, and buyer portal invitation acceptance.
- **Main Fixes:**
  * Fix 1 — Email Settings page 500 error when stored SMTP password cannot be decrypted
  * Fix 2 — Default mailer switched from `log` to `mailpit` when no team SMTP is configured
  * Fix 3 — Buyer/supplier invitation accept URL redirected to login or email verification instead of the create-account form

---

## 2. Technical Structure

### Dependencies

- Laravel Framework ^12.0
- Filament ^5.0
- Mailpit (Docker service `mailpit`, SMTP port `1025`, web UI at `/mailpit/`)
- PostgreSQL (`teams.erp_settings` JSON for per-team email config)
- `EmailTemplateService::configureMailer()` for team-level SMTP override

### Configuration

#### `.env` changes (production)

When no team-level SMTP is configured, outbound mail must use Mailpit instead of the `log` driver.

```env
MAIL_MAILER=mailpit
MAILPIT_HOST=mailpit
MAILPIT_PORT=1025
MAIL_FROM_ADDRESS="info@bpdn.id"
MAIL_FROM_NAME="${APP_NAME}"
```

| Setting | Before | After | Notes |
|---------|--------|-------|-------|
| `MAIL_MAILER` | `log` | `mailpit` | Emails appear in [Mailpit inbox](https://erpc.bpdn.id/mailpit/) |
| `MAILPIT_HOST` | _(unset, defaulted to `127.0.0.1`)_ | `mailpit` | Docker hostname on shared network |
| `MAILPIT_PORT` | _(unset, defaulted to `1025`)_ | `1025` | Mailpit SMTP port |

**Local development:** Use `MAILPIT_HOST=mailpit` when running inside Docker, or `127.0.0.1` / `host.docker.internal` when PHP runs on the host. See `config/mail.php` mailer `mailpit`.

**Do not commit** production secrets (`.env` is gitignored). Update `.env.example` locally if you want documented defaults for Mailpit.

#### Email sending priority

1. **Team SMTP configured** (`smtp_host` in Settings → Emails) → `EmailTemplateService::configureMailer()` builds a dynamic SMTP mailer.
2. **No team SMTP** → Laravel default mailer from `.env` (`mailpit` in production).

### Core Files

| File | Purpose |
|------|---------|
| `app/Filament/Pages/EmailSettings.php` | Staff email settings page (`/1/emails`) |
| `app/Services/Email/EmailTemplateService.php` | Team SMTP mailer + send helpers (unchanged; reference for decrypt pattern) |
| `app/Filament/Customer/Pages/AcceptPortalInvitation.php` | Buyer portal invitation accept form (`/buyer/invitation/{token}`) |
| `app/Filament/Supplier/Pages/AcceptPortalInvitation.php` | Supplier portal invitation accept form (`/supplier/invitation/{token}`) |
| `app/Providers/Filament/CustomerPanelProvider.php` | Registers buyer panel auth middleware (context for Fix 3) |
| `app/Support/PortalPanelConfigurator.php` | Enables `->emailVerification()` on portal panels (context for Fix 3) |
| `config/mail.php` | Default mailer + `mailpit` mailer definition |
| `tests/Feature/CustomerPortal/CustomerPortalTest.php` | HTTP regression tests for invitation page |

### Core Methods

```php
// app/Filament/Pages/EmailSettings.php
private function decryptSmtpPassword(?string $encrypted): ?string

// app/Filament/Customer/Pages/AcceptPortalInvitation.php
public static function isEmailVerificationRequired(Panel $panel): bool

// app/Services/Email/EmailTemplateService.php (existing)
public function configureMailer(TeamErpSettings $settings): ?string
public function sendWithTeamSettings(Team $team, Mailable $mailable, string|array $to, ...): void
```

---

## 3. Implementation Details

### Fix 1 — Email Settings 500 (`The MAC is invalid`)

#### Symptom

- URL: `https://app-erpc.bpdn.id/1/emails`
- HTTP 500, Laravel log: `Illuminate\Contracts\Encryption\DecryptException: The MAC is invalid`
- Stack trace: `EmailSettings.php` line 74, `Crypt::decryptString($settings->smtp_password)`

#### Root cause

Team 1 had an encrypted `smtp_password` in `teams.erp_settings` that could not be decrypted with the current `APP_KEY` (likely key rotation or environment migration). `mount()` called `Crypt::decryptString()` without a try/catch, crashing the page. `EmailTemplateService::configureMailer()` already handled decrypt failures gracefully; the settings page did not.

#### Code change

**File:** `app/Filament/Pages/EmailSettings.php`

1. Replace direct decrypt in `mount()`:

```php
$smtpPassword = $this->decryptSmtpPassword($settings->smtp_password);
```

2. Add helper (mirrors `EmailTemplateService::configureMailer()` behavior):

```php
private function decryptSmtpPassword(?string $encrypted): ?string
{
    if (empty($encrypted)) {
        return null;
    }

    try {
        return Crypt::decryptString($encrypted);
    } catch (\Throwable $e) {
        $this->logger()->warning('Failed to decrypt SMTP password on Email Settings page', [
            'error' => $e->getMessage(),
        ]);

        return null;
    }
}

private function logger(): \Psr\Log\LoggerInterface
{
    return app(\Psr\Log\LoggerInterface::class);
}
```

#### Production data fix (one-time)

Cleared orphaned invalid `smtp_password` for team 1 (password present but `smtp_host` was not set):

```bash
docker exec erpc-php php artisan tinker --execute="
\$team = App\Models\Team::find(1);
\$s = \$team->getErpSettings();
\$data = \$s->toArray();
\$data['smtp_password'] = null;
\$team->erp_settings = App\Data\TeamErpSettings::from(\$data);
\$team->save();
"
```

**Note:** Re-enter SMTP credentials in Settings → Emails after deploy if team SMTP is required. Any encrypted value created under a different `APP_KEY` must be re-saved.

---

### Fix 2 — Mailpit as default mailer

#### Symptom

Invitation and other emails were written to `storage/logs/laravel.log` (`MAIL_MAILER=log`) instead of appearing in Mailpit.

#### Expected behavior

When no team SMTP is configured, emails should be delivered to Mailpit:

- Web UI: `https://erpc.bpdn.id/mailpit/`
- SMTP: `mailpit:1025` from `erpc-php` container

#### Change

Update `.env` as shown in [Configuration](#configuration). After change:

```bash
docker exec erpc-php php artisan config:clear
```

Verify:

```bash
docker exec erpc-php php artisan tinker --execute="
echo config('mail.default') . PHP_EOL;
echo config('mail.mailers.mailpit.host') . ':' . config('mail.mailers.mailpit.port') . PHP_EOL;
"
# Expected: mailpit / mailpit:1025
```

---

### Fix 3 — Buyer invitation redirects to login / email verification

#### Symptom

- Invitation email link: `https://app-erpc.bpdn.id/buyer/invitation/{token}`
- Expected: Create-account form (name, email, password)
- Actual: HTTP 302 to `/buyer/login` or `/buyer/email-verification/prompt`
- Invitation record was valid (`portal: customer`, `accepted_at: null`)

#### Root cause

`AcceptPortalInvitation` only excluded Filament's generic `Authenticate` middleware via `$withoutRouteMiddleware`, but the buyer panel registers:

- `AuthenticatePanelUser` (custom auth middleware)
- `InitializeCustomerPortalContext`

Additionally, `PortalPanelConfigurator` enables `->emailVerification()`, and Filament attaches the `verified` middleware to every page route by default. Logged-in users with unverified email were redirected to the verification prompt.

Existing Livewire tests did not catch this because they bypass HTTP middleware.

#### Code change

**Files:**

- `app/Filament/Customer/Pages/AcceptPortalInvitation.php`
- `app/Filament/Supplier/Pages/AcceptPortalInvitation.php`

1. Expand `$withoutRouteMiddleware`:

```php
protected static string|array $withoutRouteMiddleware = [
    Authenticate::class,
    AuthenticatePanelUser::class,
    InitializeCustomerPortalContext::class, // or InitializeSupplierPortalContext on supplier page
];
```

2. Disable email verification middleware on this page:

```php
public static function isEmailVerificationRequired(Panel $panel): bool
{
    return false;
}
```

3. Add imports: `AuthenticatePanelUser`, portal context middleware, `Filament\Panel`.

#### Tests added

**File:** `tests/Feature/CustomerPortal/CustomerPortalTest.php`

- `allows guests to open the invitation accept page over http`
- `allows unverified authenticated users to open the invitation accept page over http`

Run locally:

```bash
./vendor/bin/pest --filter="allows guests to open the invitation accept page over http"
./vendor/bin/pest --filter="allows unverified authenticated users to open the invitation accept page over http"
```

---

## 4. Sync to Local & Push to GitHub

### Files to commit (code only)

```
app/Filament/Pages/EmailSettings.php
app/Filament/Customer/Pages/AcceptPortalInvitation.php
app/Filament/Supplier/Pages/AcceptPortalInvitation.php
tests/Feature/CustomerPortal/CustomerPortalTest.php
docs/FIX-IMPLEMENTATION-2026-07-07.md
```

### Local `.env` (not committed)

Apply Mailpit settings for your local stack:

```env
MAIL_MAILER=mailpit
MAILPIT_HOST=mailpit
MAILPIT_PORT=1025
```

Optional: document in `.env.example`:

```env
MAIL_MAILER=mailpit
MAILPIT_HOST=mailpit
MAILPIT_PORT=1025
```

### Suggested commit message

```
Fix email settings decrypt error and portal invitation guest access.

- Gracefully handle undecryptable team SMTP passwords on Email Settings page
- Route default outbound mail to Mailpit when team SMTP is not configured
- Allow unauthenticated access to buyer/supplier invitation accept pages
- Add HTTP regression tests for invitation route middleware
```

### Post-deploy commands (production)

```bash
docker exec erpc-php php artisan config:clear
docker exec erpc-php php artisan filament:optimize-clear
```

No `setup:upgrade` or `setup:di:compile` required — application code and config only.

---

## 5. Verification Checklist

### Email Settings page

- [ ] Open `https://app-erpc.bpdn.id/1/emails` — loads without 500
- [ ] SMTP password field empty if previous value was invalid
- [ ] Save settings and send test email works after re-entering SMTP (if used)

### Mailpit

- [ ] Send buyer portal invitation from admin
- [ ] Email appears in `https://erpc.bpdn.id/mailpit/`
- [ ] From address shows `info@bpdn.id` (or team override)

### Buyer invitation

- [ ] Open invitation link in incognito (no session) — HTTP 200, "Create Buyer Portal Account"
- [ ] Form shows invited email pre-filled
- [ ] Submit password — account created, redirect to buyer login
- [ ] Same link works when logged in as another unverified buyer user (no redirect to email verification)

### Automated tests

- [ ] `CustomerPortalTest` invitation HTTP tests pass locally

---

## 6. Business Rules & Integration Points

### Email configuration layers

| Layer | Location | Behavior |
|-------|----------|----------|
| Global default | `.env` → `config/mail.php` | Mailpit in production until real SMTP is configured |
| Per-team | Settings → Emails → `teams.erp_settings` | Overrides global when `smtp_host` is set |
| Templates | `EmailTemplate` model + Settings → Emails | Buyer quote/order, supplier order, delivery order |

### Portal invitation flow

```
InvitePortalUser::execute()
  → PortalInvitation created (token)
  → Email: PortalUserInvitationMail with accept URL
  → AcceptPortalInvitation page (guest-accessible)
  → AcceptPortalInvitation action (creates User + CompanyPortalUser)
  → Redirect to panel login
```

### Events / middleware

| Middleware | Panel | Invitation page |
|------------|-------|-------------------|
| `AuthenticatePanelUser` | All authenticated routes | **Excluded** |
| `InitializeCustomerPortalContext` | Buyer panel auth stack | **Excluded** |
| `verified` (email verification) | All pages by default | **Disabled** via `isEmailVerificationRequired()` |

---

## 7. Related Documentation

- `openspec/changes/archive/2026-02-02-add-email-settings/` — original email settings design
- `app/Actions/Portal/InvitePortalUser.php` — invitation URL generation
- `config/mail.php` — mailpit mailer definition
- `docs/FIX-IMPLEMENTATION-2026-06-23.md` — prior production fix log format

---

**Applied on production:** 2026-07-07  
**Author:** Cursor agent session (production hotfix)  
**Status:** Verified on production (Email Settings loads, invitation URL HTTP 200, Mailpit receiving mail)
