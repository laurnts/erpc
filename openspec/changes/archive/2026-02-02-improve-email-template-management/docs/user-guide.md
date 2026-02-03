# Email Template Management User Guide

## Overview

The Email Template Management system allows you to create, manage, and select custom email templates for different document types. You can create multiple template variations and choose which one to use for each document type.

## Accessing Email Templates

### Email Settings Page
1. Navigate to **Settings** → **Email Settings** in the main menu
2. Scroll to any email template section (Buyer Quote, Buyer Order, Supplier Order, Delivery Order)
3. Use the dropdown to select a template or create a new one

### Email Templates Management Page
1. Navigate to **Settings** → **Email Templates** in the main menu
2. View all your templates in a list
3. Create, edit, or delete templates

## Understanding Email Templates

### What are Email Templates?

Email templates define the content and structure of emails sent when sharing documents (quotes, orders, invoices, shipments) with buyers or suppliers.

### Template Types

The system supports four template types:

1. **Buyer Quote** - Used when sending quote PDFs to buyers
2. **Buyer Order** - Used when sending order/invoice PDFs to buyers
3. **Supplier Order** - Used when sending purchase order PDFs to suppliers
4. **Delivery Order** - Used when sending delivery order PDFs to buyers

### Default Templates

Each template type has a default template that is automatically used if you don't select a custom template. Default templates are pre-configured and extracted from the system's blade files.

## Creating a New Template

### Method 1: From Email Settings Page

1. Navigate to **Settings** → **Email Settings**
2. Find the template section you want (e.g., "Buyer Quote")
3. Click the **"+"** button next to the template dropdown
4. Fill in the form:
   - **Template Type**: Pre-filled based on the section (cannot be changed)
   - **Template Name**: Enter a descriptive name (e.g., "Quote Template - Q4 2024")
   - **Template Content**: Enter your email content (see Template Variables below)
5. Click **"Load Default Template"** button (optional) to start with the default template content
6. Click **"Create"** to save

### Method 2: From Email Templates Page

1. Navigate to **Settings** → **Email Templates**
2. Click **"Add New Template"** button in the header
3. Fill in the form:
   - **Template Type**: Select from dropdown
   - **Template Name**: Enter a descriptive name
   - **Template Content**: Enter your email content
4. Click **"Load Default Template"** button (optional) to start with the default template content
5. Click **"Create"** to save

### Using Load Default Template

The **"Load Default Template"** button automatically fills the Template Content field with the system's default template for the selected type. This is helpful when:
- You want to customize the default template
- You need a starting point for your custom template
- You want to see what the default template looks like

**Note**: Make sure to select a Template Type before clicking "Load Default Template".

## Selecting a Template

### In Email Settings Page

1. Navigate to **Settings** → **Email Settings**
2. Find the template section (e.g., "Buyer Quote")
3. Use the dropdown to select:
   - **Default Template** - Uses the system default
   - **Your Custom Templates** - Lists all templates you've created for that type
4. The selected template will be used for all emails of that type

### Template Selection Behavior

- Only one template can be selected per template type
- Selecting "Default Template" means the system default will be used
- Your selection is saved automatically when you save Email Settings

## Editing a Template

### From Email Templates Page

1. Navigate to **Settings** → **Email Templates**
2. Find the template you want to edit
3. Click on the row or click the **"Edit"** action button
4. Modify the template fields:
   - **Template Name**: Can be changed
   - **Template Type**: Cannot be changed (disabled)
   - **Template Content**: Can be modified
5. Click **"Save"** to update

**Note**: Default templates (system templates) cannot be edited. Their fields are disabled.

## Deleting a Template

1. Navigate to **Settings** → **Email Templates**
2. Find the template you want to delete
3. Click the **"Delete"** action button
4. Confirm the deletion

### What Happens When You Delete a Template?

- If the deleted template was currently selected in Email Settings, the selection automatically changes to "Default Template"
- You'll receive a notification explaining that the default template will now be used
- The template is permanently removed from your template library

**Note**: Default templates (system templates) cannot be deleted.

## Template Content

### Template Variables

You can use variables in your template content that will be automatically replaced with actual data when emails are sent:

| Variable | Description | Available In |
|----------|-------------|--------------|
| `{{supplier_name}}` | Supplier company name | All templates |
| `{{buyer_name}}` | Buyer company name | All templates |
| `{{quote_number}}` | Quote reference number | Buyer Quote |
| `{{order_number}}` | Order reference number | Buyer Order, Supplier Order |
| `{{invoice_number}}` | Invoice reference number | Buyer Order |
| `{{shipment_number}}` | Shipment reference number | Delivery Order |
| `{{request_number}}` | Related request number | All templates |
| `{{valid_until}}` | Quote expiration date | Buyer Quote |
| `{{total_amount}}` | Total amount (formatted) | Buyer Quote, Buyer Order, Supplier Order |
| `{{invoice_date}}` | Invoice date | Buyer Order |
| `{{due_date}}` | Payment due date | Buyer Order |
| `{{order_date}}` | Order date | Buyer Order, Supplier Order |
| `{{delivery_date}}` | Expected delivery date | Delivery Order |
| `{{shipment_date}}` | Shipment date | Delivery Order |
| `{{tracking_number}}` | Tracking number | Delivery Order |
| `{{delivery_address}}` | Delivery address | Delivery Order |

### Template Content Types

#### Simple Content Templates

Enter plain text with variables. The content will be inserted into the default email template structure:

```
Dear {{buyer_name}},

Please find attached Quote {{quote_number}}.

Quote Details:
- Quote Number: {{quote_number}}
- Valid Until: {{valid_until}}
- Total Amount: {{total_amount}}

Please review and let us know if you have any questions.

Best regards,
```

#### Full HTML Templates

You can also create complete HTML email templates. If your template content includes full HTML structure (DOCTYPE, html, head, body tags), it will be rendered as a complete email document without the default wrapper.

**Example Full HTML Template:**

```html
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quote {{quote_number}}</title>
</head>
<body>
    <h1>Quote {{quote_number}}</h1>
    <p>Dear {{buyer_name}},</p>
    <p>Please find attached quote details.</p>
</body>
</html>
```

### Example Template Content

**Simple Quote Template:**

```
Dear {{buyer_name}},

Thank you for your inquiry. Please find attached Quote {{quote_number}}.

**Quote Details:**
- Quote Number: {{quote_number}}
- Valid Until: {{valid_until}}
- Total Amount: {{total_amount}}

Please review and let us know if you have any questions.

Best regards,
{{sender_name}}
```

## Managing Templates

### Viewing All Templates

1. Navigate to **Settings** → **Email Templates**
2. View the list showing:
   - **Template Name**: Name you gave the template
   - **Type**: Template type (Buyer Quote, Buyer Order, etc.)
   - **Status**: Shows "(Default)" for system templates
   - **Sender Email**: Email address (if configured in Email Settings)
   - **Created/Updated**: Timestamps

### Filtering Templates

- **Filter by Type**: Use the Type filter dropdown to show only specific template types
- **Filter by Status**: Use the Status filter to show default or custom templates
- **Search**: Use the search box to find templates by name

### Bulk Actions

- Select multiple templates using checkboxes
- Use bulk delete to remove multiple templates at once

## Best Practices

1. **Use Descriptive Names**: Name your templates clearly (e.g., "Quote Template - Q4 2024" instead of "Template 1")
2. **Test Templates**: After creating or editing a template, send a test email to verify it looks correct
3. **Use Variables**: Leverage template variables to make templates dynamic and reusable
4. **Start with Default**: Use "Load Default Template" to start with a proven template structure
5. **Keep Backups**: Before making major changes, note the template name so you can recreate it if needed
6. **One Template Per Purpose**: Create separate templates for different scenarios rather than one complex template

## Common Questions

### Can I use the same template for multiple types?

No, each template type requires its own template. However, you can create similar templates for different types and copy the content.

### What happens if I delete a template that's currently selected?

The system automatically switches to "Default Template" and notifies you. Your emails will continue to work using the default template.

### Can I edit default templates?

No, default templates are system templates and cannot be edited. However, you can load the default template content, modify it, and save it as a new custom template.

### Can I use HTML in templates?

Yes, you can use HTML. If you include a complete HTML document structure (DOCTYPE, html, head, body), it will be rendered as a full email. Otherwise, HTML tags will be rendered within the default email wrapper.

### How do template variables work?

Variables are replaced with actual data when emails are sent. Use double curly braces: `{{variable_name}}`. Variables are case-sensitive.

### Where are sender email, CC, and BCC configured?

These are configured in **Email Settings** page, not in individual templates. Templates use the Email Settings configuration for sender, CC, and BCC.

## Troubleshooting

### Template Not Showing in Dropdown

- Verify the template type matches the section you're viewing
- Check that the template belongs to your team
- Refresh the page

### Variables Not Replacing

- Ensure variable names match exactly (case-sensitive)
- Use double curly braces: `{{variable_name}}`
- Check that the variable is available for that template type

### Template Content Not Loading

- Make sure Template Type is selected before clicking "Load Default Template"
- Check that the default template file exists in the system
- Try refreshing the page

### Cannot Edit/Delete Template

- Default templates (system templates) cannot be edited or deleted
- Only templates you created can be modified
- Check that you have the necessary permissions

## Support

If you encounter issues:
1. Check the error message in the notification
2. Verify template type matches your use case
3. Review template content for syntax errors
4. Contact your system administrator
