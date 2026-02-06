# Email Template Management Migration Guide

## Overview

This guide helps you migrate from the old email template system (stored in `TeamErpSettings` as arrays) to the new email template management system (stored in `email_templates` database table).

## Prerequisites

- Database backup completed
- Access to run migrations and seeders
- Understanding of your current email template configuration

## Migration Steps

### Step 1: Run Database Migration

The migration creates the `email_templates` table:

```bash
php artisan migrate
```

This will create:
- `email_templates` table with all required columns
- Indexes on `team_id`, `type`, and `(team_id, type)`
- Foreign key constraint on `team_id`

### Step 2: Seed Default Templates

Default templates are automatically seeded from blade files:

```bash
php artisan db:seed --class=EmailTemplateSeeder
```

This creates default templates for:
- Buyer Quote (`quote-to-buyer.blade.php`)
- Buyer Order (`buyer-order-to-buyer.blade.php`)
- Supplier Order (`purchase-order-to-supplier.blade.php`)
- Delivery Order (`shipment-to-buyer.blade.php`)

### Step 3: Migrate Existing Template Content (Optional)

If you have existing custom templates in `TeamErpSettings`, you can migrate them to the new system.

#### Option A: Manual Migration

1. Navigate to **Settings** → **Email Settings**
2. For each template section:
   - Review existing template content
   - Click "+" to create new template
   - Copy content from old system
   - Save as new template
   - Select the new template in dropdown

#### Option B: Automated Migration Script

Create a migration script to automatically migrate existing templates:

```php
<?php

use App\Models\Team;
use App\Models\EmailTemplate;
use App\Data\TeamErpSettings;

// Run this in tinker or create a command
$teams = Team::all();

foreach ($teams as $team) {
    $settings = $team->getErpSettings();
    
    // Migrate buyer_quote template
    if (!empty($settings->email_template_buyer_quote)) {
        $template = EmailTemplate::create([
            'team_id' => $team->id,
            'type' => EmailTemplate::TYPE_BUYER_QUOTE,
            'name' => 'Migrated Buyer Quote Template',
            'content' => $settings->email_template_buyer_quote['content'] ?? '',
            'sender_email' => $settings->email_template_buyer_quote['sender_email'] ?? null,
            'cc_emails' => $settings->email_template_buyer_quote['cc_emails'] ?? null,
            'bcc_emails' => $settings->email_template_buyer_quote['bcc_emails'] ?? null,
            'is_default' => false,
        ]);
        
        // Update TeamErpSettings with template ID
        $settings->email_template_buyer_quote_id = $template->id;
    }
    
    // Repeat for other template types...
    // email_template_buyer_order
    // email_template_supplier_order
    // email_template_delivery_order
    
    $team->update(['erp_settings' => $settings->toArray()]);
}
```

### Step 4: Verify Migration

1. **Check Templates Created**:
   ```sql
   SELECT COUNT(*) FROM email_templates;
   SELECT team_id, type, name FROM email_templates ORDER BY team_id, type;
   ```

2. **Check Template Selection**:
   ```sql
   SELECT id, name, 
          erp_settings->>'email_template_buyer_quote_id' as buyer_quote_id,
          erp_settings->>'email_template_buyer_order_id' as buyer_order_id
   FROM teams
   WHERE erp_settings->>'email_template_buyer_quote_id' IS NOT NULL
      OR erp_settings->>'email_template_buyer_order_id' IS NOT NULL;
   ```

3. **Test Email Sending**:
   - Navigate to Email Settings
   - Send a test email
   - Verify email uses correct template

### Step 5: Update Permissions

Ensure permissions are seeded:

```bash
php artisan db:seed --class=ErpPermissionSeeder
```

This adds:
- `view email templates`
- `create email templates`
- `update email templates`
- `delete email templates`

## Rollback Plan

If you need to rollback:

### Step 1: Restore Old Template Fields

The old template fields are still in `TeamErpSettings` DTO, so they can be restored if needed.

### Step 2: Revert Code Changes

Revert to previous commit before email template management changes.

### Step 3: Restore Database

Restore database from backup taken before migration.

## Post-Migration Checklist

- [ ] Default templates seeded successfully
- [ ] Existing templates migrated (if applicable)
- [ ] Template selections saved in TeamErpSettings
- [ ] Email sending works with new templates
- [ ] Email Templates page accessible
- [ ] Can create new templates
- [ ] Can edit existing templates
- [ ] Can delete templates
- [ ] Template deletion fallback works
- [ ] Load Default Template button works
- [ ] Permissions configured correctly

## Common Issues

### Issue: Templates Not Showing in Dropdown

**Solution**:
- Verify templates exist: `SELECT * FROM email_templates WHERE team_id = ?`
- Check template type matches section type
- Clear cache: `php artisan cache:flush`

### Issue: Default Templates Not Found

**Solution**:
- Re-run seeder: `php artisan db:seed --class=EmailTemplateSeeder`
- Verify blade files exist in `resources/views/emails/`
- Check `is_default = true` and `team_id IS NULL` in database

### Issue: Template Selection Not Saving

**Solution**:
- Check TeamErpSettings DTO has template ID fields
- Verify form field names match DTO property names
- Check for validation errors in logs

### Issue: Email Sending Uses Wrong Template

**Solution**:
- Verify template ID in TeamErpSettings matches template in database
- Check template type matches email type
- Verify EmailTemplateService fallback logic

## Data Cleanup (Optional)

After verifying migration is successful, you can optionally remove old template fields from `TeamErpSettings`:

1. **Create Migration**:
```php
// Remove old template array fields (keep for now as backup)
// Can be removed in future version after confirming no issues
```

2. **Update Code**:
- Remove old template array fields from `TeamErpSettings` DTO
- Remove references to old fields in code

**Note**: It's recommended to keep old fields for a few versions as backup before removing them.

## Support

If you encounter issues during migration:

1. Check application logs: `storage/logs/laravel.log`
2. Verify database state: Check `email_templates` table
3. Test email sending: Use test email functionality
4. Review migration script output: Check for errors
5. Contact support: Provide migration logs and error messages

## Version Compatibility

- **Minimum Laravel Version**: 12.x
- **Minimum PHP Version**: 8.4
- **Database**: PostgreSQL 15+

## Additional Resources

- User Guide: See `docs/user-guide.md`
- Developer Guide: See `docs/developer-guide.md`
- Design Documentation: See `design.md`
- Implementation Tasks: See `tasks.md`
