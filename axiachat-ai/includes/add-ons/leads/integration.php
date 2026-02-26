<?php
/**
 * Leads Integration - Register tool with AI Tools system
 * 
 * Dynamically registers the save_lead tool based on the selected destination.
 * For CF7/WPForms, the tool schema uses the form's actual fields.
 * For Internal/CPT, uses default lead fields.
 * 
 * @package AIChat
 * @subpackage Leads
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register the save_lead tool
 */
add_action( 'init', 'aichat_leads_register_tool', 25 );
function aichat_leads_register_tool() {
    
    // Only register if AI Tools API is available
    if ( ! function_exists( 'aichat_register_tool_safe' ) ) {
        return;
    }
    
    // Check if leads add-on is enabled
    if ( ! get_option( 'aichat_addon_leads_enabled', 1 ) ) {
        return;
    }
    
    // Get the tool schema based on destination
    $schema = aichat_leads_get_tool_schema();
    $description = aichat_leads_get_tool_description();
    
    aichat_register_tool_safe( 'save_lead', [
        'type' => 'function',
        'name' => 'save_lead',
        'description' => $description,
        'activity_label' => __( 'Saving contact information...', 'axiachat-ai' ),
        'schema' => $schema,
        'callback' => 'aichat_leads_tool_callback',
        'timeout' => 10,
        'parallel' => false,
        'max_calls' => 1,
    ] );
    
    // Also register a macro for easy enabling in bot settings
    if ( function_exists( 'aichat_register_macro' ) ) {
        aichat_register_macro( [
            'name'        => 'lead_capture',
            'label'       => __( 'Lead Capture', 'axiachat-ai' ),
            'description' => __( 'Enables the bot to capture and save customer contact information during conversations.', 'axiachat-ai' ),
            'tools'       => [ 'save_lead' ],
            'source'      => 'local',
            'source_ref'  => 'axiachat_leads',
        ] );
    }
}

/**
 * Get the tool description based on destination
 */
function aichat_leads_get_tool_description() {
    $settings = AIChat_Leads_Manager::get_settings();
    $destination = $settings['destination'] ?? 'internal';
    
    $base = 'Saves customer contact information (lead). Use this tool when a user provides contact details. IMPORTANT: Always ask for confirmation before saving. Only save data that the user has explicitly provided.';
    
    if ( $destination === 'cf7' && ! empty( $settings['cf7_form_id'] ) ) {
        $fields = AIChat_Leads_Adapter_CF7::get_form_fields( $settings['cf7_form_id'] );
        if ( ! empty( $fields ) ) {
            $field_names = array_column( $fields, 'name' );
            $base .= ' Available fields: ' . implode( ', ', $field_names ) . '.';
        }
    } elseif ( $destination === 'wpforms' && ! empty( $settings['wpforms_form_id'] ) ) {
        $fields = AIChat_Leads_Adapter_WPForms::get_form_fields( $settings['wpforms_form_id'] );
        if ( ! empty( $fields ) ) {
            $field_labels = array_column( $fields, 'label' );
            $base .= ' Available fields: ' . implode( ', ', $field_labels ) . '.';
        }
    } else {
        $base .= ' Available fields: name, email, phone, company, interest, notes.';
    }
    
    return $base;
}

/**
 * Get the tool schema based on destination
 */
function aichat_leads_get_tool_schema() {
    $settings = AIChat_Leads_Manager::get_settings();
    $destination = $settings['destination'] ?? 'internal';
    
    // For CF7, use form fields
    if ( $destination === 'cf7' && ! empty( $settings['cf7_form_id'] ) ) {
        return aichat_leads_get_cf7_schema( $settings['cf7_form_id'] );
    }
    
    // For WPForms, use form fields
    if ( $destination === 'wpforms' && ! empty( $settings['wpforms_form_id'] ) ) {
        return aichat_leads_get_wpforms_schema( $settings['wpforms_form_id'] );
    }
    
    // Default schema for Internal/CPT
    return aichat_leads_get_default_schema();
}

/**
 * Get default schema for Internal/CPT destination
 */
function aichat_leads_get_default_schema() {
    return [
        'type' => 'object',
        'properties' => [
            'nombre' => [
                'type' => 'string',
                'description' => 'Customer full name',
            ],
            'email' => [
                'type' => 'string',
                'description' => 'Contact email address (valid email format)',
            ],
            'telefono' => [
                'type' => 'string',
                'description' => 'Phone number with or without international prefix',
            ],
            'empresa' => [
                'type' => 'string',
                'description' => 'Company or organization name (optional)',
            ],
            'interes' => [
                'type' => 'string',
                'description' => 'Product/service of interest or reason for contact',
            ],
            'notas' => [
                'type' => 'string',
                'description' => 'Additional relevant information from the conversation',
            ],
        ],
        'required' => [],
        'additionalProperties' => false,
    ];
}

/**
 * Get schema from CF7 form fields
 */
function aichat_leads_get_cf7_schema( $form_id ) {
    if ( ! class_exists( 'AIChat_Leads_Adapter_CF7' ) ) {
        return aichat_leads_get_default_schema();
    }
    
    $fields = AIChat_Leads_Adapter_CF7::get_form_fields( $form_id );
    
    if ( empty( $fields ) ) {
        return aichat_leads_get_default_schema();
    }
    
    $properties = [];
    $required = [];
    
    foreach ( $fields as $field ) {
        $name = $field['name'];
        $type = $field['type'];
        
        // Skip hidden and submit fields
        if ( in_array( $type, [ 'submit', 'hidden', 'acceptance', 'quiz', 'recaptcha' ], true ) ) {
            continue;
        }
        
        // Build property
        $prop = [
            'type' => 'string',
            'description' => ucfirst( str_replace( [ '-', '_', 'your-' ], [ ' ', ' ', '' ], $name ) ),
        ];
        
        // Add type hints
        if ( $type === 'email' ) {
            $prop['description'] = 'Email address (valid email format)';
        } elseif ( $type === 'tel' ) {
            $prop['description'] = 'Phone number';
        } elseif ( $type === 'textarea' ) {
            $prop['description'] = 'Message or additional notes';
        } elseif ( $type === 'url' ) {
            $prop['description'] = 'Website URL';
        }
        
        $properties[ $name ] = $prop;
        
        // Check if field is required (CF7 uses * in field name or basetype ends with *)
        if ( strpos( $field['type'], '*' ) !== false ) {
            $required[] = $name;
        }
    }
    
    if ( empty( $properties ) ) {
        return aichat_leads_get_default_schema();
    }
    
    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => $required,
        'additionalProperties' => false,
    ];
}

/**
 * Get schema from WPForms form fields
 */
function aichat_leads_get_wpforms_schema( $form_id ) {
    if ( ! class_exists( 'AIChat_Leads_Adapter_WPForms' ) ) {
        return aichat_leads_get_default_schema();
    }
    
    $fields = AIChat_Leads_Adapter_WPForms::get_form_fields( $form_id );
    
    if ( empty( $fields ) ) {
        return aichat_leads_get_default_schema();
    }
    
    $properties = [];
    $required = [];
    
    foreach ( $fields as $field ) {
        $field_id = 'field_' . $field['id'];
        $label = $field['label'];
        $type = $field['type'];
        
        // Skip certain field types
        if ( in_array( $type, [ 'divider', 'html', 'pagebreak', 'captcha', 'stripe-credit-card', 'paypal-commerce' ], true ) ) {
            continue;
        }
        
        // Build property with label as key for readability
        $prop_name = sanitize_key( $label );
        if ( empty( $prop_name ) ) {
            $prop_name = $field_id;
        }
        
        $prop = [
            'type' => 'string',
            'description' => $label,
        ];
        
        // Add type hints
        if ( $type === 'email' ) {
            $prop['description'] = $label . ' (valid email format)';
        } elseif ( $type === 'phone' ) {
            $prop['description'] = $label . ' (phone number)';
        }
        
        // Store field_id mapping in description for callback
        $prop['x-wpforms-field-id'] = $field['id'];
        
        $properties[ $prop_name ] = $prop;
    }
    
    if ( empty( $properties ) ) {
        return aichat_leads_get_default_schema();
    }
    
    return [
        'type' => 'object',
        'properties' => $properties,
        'required' => $required,
        'additionalProperties' => false,
    ];
}

/**
 * Tool callback function
 * 
 * @param array $args Arguments from the LLM
 * @param array $context Execution context
 * @return array Result to return to the LLM
 */
function aichat_leads_tool_callback( $args, $context = [] ) {
    
    // Ensure manager class is loaded
    if ( ! class_exists( 'AIChat_Leads_Manager' ) ) {
        return [
            'ok'      => false,
            'error'   => 'manager_not_loaded',
            'message' => 'Lead capture system is not available.',
        ];
    }
    
    // Call the manager to save the lead
    $result = AIChat_Leads_Manager::save( $args, $context );
    
    return $result;
}

/**
 * Add lead capture instructions hint to bot system prompt
 * (Optional enhancement - can be enabled via filter)
 */
add_filter( 'aichat_system_prompt_additions', 'aichat_leads_prompt_hint', 10, 2 );
function aichat_leads_prompt_hint( $additions, $bot ) {
    
    // Only add if leads is enabled and filter allows
    if ( ! apply_filters( 'aichat_leads_add_prompt_hint', false, $bot ) ) {
        return $additions;
    }
    
    $hint = "\n\n[LEAD CAPTURE] You have the ability to save customer contact information using the save_lead tool. When a user shares contact details (name, email, phone, etc.), offer to save their information for follow-up. Always ask for confirmation before saving.";
    
    $additions[] = $hint;
    
    return $additions;
}
