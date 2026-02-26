# Leads Capture Add-on

## Overview

The Leads add-on enables AI bots to capture customer contact information during chat conversations. Leads can be saved to multiple destinations including the internal database, WordPress Custom Post Types, Contact Form 7, or WPForms.

## Features

- **Multi-destination support**: Save leads to internal table, CPT, CF7, or WPForms
- **Customizable fields**: Capture name, email, phone, company, interest, and custom fields
- **Status tracking**: Mark leads as new, contacted, converted, or discarded
- **Email notifications**: Get notified when new leads are captured
- **GDPR compliant**: Optional IP hashing, data retention policies
- **Export functionality**: Export leads to CSV
- **Bot-aware**: Tracks which bot captured each lead

## Usage

### For Bot Creators

Add instructions to your bot's system prompt to enable lead capture:

```
LEAD CAPTURE:
When a user shows interest in our products/services:
1. Confirm their interest
2. Politely ask for their contact information (name, email, phone)
3. Validate the email format before proceeding
4. Summarize the captured data and ask for confirmation
5. Only after explicit confirmation, use guardar_lead() to save the data
6. Confirm to the user that their information has been saved

NEVER save data without user confirmation.
```

### Tool: `guardar_lead`

The add-on registers a tool called `guardar_lead` that the AI can invoke:

**Parameters:**
- `nombre` (required): Customer's full name
- `email` (optional): Email address
- `telefono` (optional): Phone number
- `empresa` (optional): Company name
- `interes` (optional): Product/service of interest
- `notas` (optional): Additional notes from conversation
- `campos_extra` (optional): Object with custom key-value pairs

**Example call:**
```json
{
  "nombre": "Juan García",
  "email": "juan@example.com",
  "telefono": "+34 612 345 678",
  "empresa": "ACME Corp",
  "interes": "Enterprise plan",
  "notas": "Interested in annual billing, asked about API access"
}
```

## Configuration

Navigate to **AxiaChat AI → Leads** in the WordPress admin.

### Settings Tab

- **Enable Lead Capture**: Toggle the add-on on/off
- **Required Fields**: Set email and/or phone as mandatory
- **Privacy**: Enable IP hash storage for tracking
- **Data Retention**: Auto-delete leads after X days (GDPR compliance)
- **Destinations**: Choose where to save leads
- **Notifications**: Enable email alerts for new leads

### Destinations

1. **Internal Database**: Default storage in `wp_aichat_leads` table
2. **Custom Post Type**: Creates `aichat_lead` posts viewable in WP admin
3. **Contact Form 7**: Submits leads through an existing CF7 form
4. **WPForms**: Creates entries in a WPForms form

### Field Mapping (CF7/WPForms)

When using form plugins, map lead fields to form fields:

| Lead Field | Example CF7 Name | Example WPForms ID |
|------------|------------------|-------------------|
| nombre     | your-name        | 1                 |
| email      | your-email       | 2                 |
| telefono   | your-phone       | 3                 |
| empresa    | your-company     | 4                 |
| interes    | your-subject     | 5                 |
| notas      | your-message     | 6                 |

## Database

The add-on creates a table `wp_aichat_leads` with the following structure:

```sql
CREATE TABLE wp_aichat_leads (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    session_id VARCHAR(64),
    bot_slug VARCHAR(100),
    conversation_id BIGINT UNSIGNED,
    nombre VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    telefono VARCHAR(50),
    empresa VARCHAR(255),
    interes TEXT,
    notas TEXT,
    campos_extra LONGTEXT,
    destino VARCHAR(32) DEFAULT 'internal',
    destino_ref VARCHAR(100),
    estado ENUM('nuevo','contactado','convertido','descartado'),
    ip_hash VARCHAR(64),
    created_at DATETIME,
    updated_at DATETIME,
    PRIMARY KEY (id)
);
```

## Hooks & Filters

### Filters

```php
// Modify available destinations
add_filter('aichat_leads_destinations', function($destinations) {
    $destinations['custom_crm'] = [
        'id' => 'custom_crm',
        'name' => 'My CRM',
        'available' => true,
    ];
    return $destinations;
});

// Add hint to bot system prompt
add_filter('aichat_leads_add_prompt_hint', '__return_true');
```

### Actions

The add-on uses standard WordPress AJAX hooks:
- `wp_ajax_aichat_leads_save_settings`
- `wp_ajax_aichat_leads_get_lead`
- `wp_ajax_aichat_leads_update_status`
- `wp_ajax_aichat_leads_delete`
- `wp_ajax_aichat_leads_export`

## Creating Custom Adapters

To add a new destination, create an adapter class:

```php
class My_CRM_Adapter {
    public function save($lead, $settings) {
        // Your CRM API call here
        return ['ok' => true, 'ref' => $crm_id, 'dest' => 'my_crm'];
    }
}

// Register the adapter
AIChat_Leads_Manager::register_adapter('my_crm', new My_CRM_Adapter());
```

## Changelog

### 1.0.0
- Initial release
- Internal database storage
- Custom Post Type support
- Contact Form 7 integration
- WPForms integration
- Lead status management
- CSV export
- Email notifications
- GDPR compliance features
