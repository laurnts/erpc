# Email Settings User Guide

## Overview

The Email Settings page allows you to configure email templates, sender information, SMTP settings, and branding for all emails sent from your ERP system.

## Accessing Email Settings

1. Navigate to **Settings** → **Email Settings** in the main menu
2. You must be a team owner or admin to access these settings

## Configuration Sections

### 1. Email Configuration

#### Default Sender Email
- **Required field**
- The default email address used as the sender for all emails
- Example: `info@yourcompany.com`
- **Note**: If using Gmail SMTP, verify this address in Gmail Settings → Accounts → Send mail as to send from a different domain

#### Default Sender Name
- The name displayed as the sender for all emails
- Example: `Your Company Name`
- Falls back to application name if not set

#### Email Logo
- Upload a logo image to display in email headers
- Supported formats: PNG, JPG, SVG
- Maximum file size: 2MB
- Logo will appear at the top of all email templates

#### Email Signature
- Optional signature text added to the bottom of all emails
- Supports plain text and basic formatting
- Example:
  ```
  Best regards,
  John Doe
  Sales Manager
  Your Company Name
  ```

### 2. SMTP Configuration

Configure custom SMTP settings to send emails through your own email server.

#### SMTP Host
- Your SMTP server hostname
- Examples:
  - Gmail: `smtp.gmail.com`
  - Outlook: `smtp-mail.outlook.com`
  - Custom: `mail.yourdomain.com`
- **Leave empty** to use default mailer configuration from `.env`

#### SMTP Port
- Common ports:
  - **587** (TLS) - Recommended for most providers
  - **465** (SSL) - Used by Gmail
  - **25** (No encryption) - Not recommended
- Default: 587

#### SMTP Username
- Your SMTP account username
- Usually your full email address
- Example: `yourname@gmail.com`

#### SMTP Password
- Your SMTP account password
- **For Gmail with 2FA**: Use an App Password (generate at https://myaccount.google.com/apppasswords)
- **IMPORTANT**: Remove all spaces from App Password before pasting (e.g., "abcd efgh" → "abcdefgh")
- Password is encrypted when saved

#### Encryption
- **TLS** - Use with port 587 (recommended)
- **SSL** - Use with port 465
- **None** - Not recommended

#### Testing SMTP Connection
- Click "Test SMTP Connection" button to verify your SMTP settings
- You'll see a success or error message with details

### 3. Email Templates

Configure custom email templates for different document types. Each template can have:
- Custom content with variables
- Override sender email (optional)
- CC email addresses
- BCC email addresses

#### Available Email Templates

1. **Buyer Quote** - Sent when sending quote PDFs to buyers
2. **Buyer Order** - Sent when sending order/invoice PDFs to buyers
3. **Supplier Order** - Sent when sending purchase order PDFs to suppliers
4. **Delivery Order** - Sent when sending delivery order PDFs to buyers

#### Template Content

- Enter custom text for the email body
- Use template variables (see below) for dynamic content
- Leave empty to use the default email template
- **Note**: Do not paste HTML code here - use plain text with variables

#### Template Variables

Available variables that can be used in template content:

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

#### Example Template Content

```
Dear {{buyer_name}},

Please find attached Quote {{quote_number}}.

**Quote Details:**
- Quote Number: {{quote_number}}
- Valid Until: {{valid_until}}
- Total Amount: {{total_amount}}

Please review and let us know if you have any questions.

Best regards,
{{sender_name}}
```

#### Sender Email (Optional)
- Override the default sender email for this specific template
- Leave empty to use the default sender email
- **Note**: If using Gmail SMTP, verify the address in Gmail Settings → Send mail as

#### CC Emails
- Comma-separated list of email addresses to CC
- Example: `manager@company.com, sales@company.com`
- Leave empty if not needed

#### BCC Emails
- Comma-separated list of email addresses to BCC
- Example: `archive@company.com`
- Leave empty if not needed

### 4. Test Email

Send a test email to verify your email configuration.

#### Steps:
1. Enter a test email address
2. Click "Send Test Email"
3. Check your inbox (and spam folder) for the test email
4. Verify:
   - Email arrives successfully
   - Sender address is correct
   - Logo displays correctly
   - Signature appears at bottom

## Common Issues and Solutions

### Emails Not Arriving

1. **Check Spam Folder**: Emails may be marked as spam
2. **Verify SMTP Settings**: Use "Test SMTP Connection" to verify
3. **Gmail Sender Mismatch**: If using Gmail SMTP with different sender:
   - Verify sender address in Gmail Settings → Send mail as
   - Or use Gmail address as sender

### Gmail Authentication Errors

- **Error: "Application-specific password required"**
  - Your Gmail account has 2FA enabled
  - Generate an App Password at https://myaccount.google.com/apppasswords
  - Use the App Password (without spaces) in SMTP Password field

- **Error: "Username and Password not accepted"**
  - Ensure App Password has NO spaces
  - Regenerate App Password if issue persists
  - Verify port/encryption: 465=SSL, 587=TLS

### Template Variables Not Replacing

- Ensure variable names match exactly (case-sensitive)
- Use double curly braces: `{{variable_name}}`
- Check that the variable is available for that template type

## Best Practices

1. **Test Before Production**: Always send a test email after configuration changes
2. **Use Verified Sender**: When using Gmail SMTP, verify sender addresses in Gmail
3. **Keep Templates Simple**: Use plain text with variables, not HTML
4. **Secure Passwords**: Never share SMTP passwords
5. **Regular Testing**: Test email functionality periodically

## Support

If you encounter issues:
1. Check the error message in the notification
2. Review SMTP connection test results
3. Check application logs: `storage/logs/laravel.log`
4. Contact your system administrator
