<?php
/**
 * Live Chat REST API Endpoints
 * 
 * Provides webhooks for external integrations (Slack, etc.)
 * 
 * @package AIChat
 * @subpackage LiveChat
 * @since 2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Register REST routes
 */
add_action( 'rest_api_init', 'aichat_livechat_register_routes' );

function aichat_livechat_register_routes() {
    // Slack interactive webhook (button clicks)
    register_rest_route( 'aichat/v1', '/livechat/slack', [
        'methods'             => 'POST',
        'callback'            => 'aichat_livechat_slack_webhook',
        'permission_callback' => '__return_true', // Verified via Slack signature
    ] );
    
    // Slack events webhook (messages in threads)
    register_rest_route( 'aichat/v1', '/livechat/slack/events', [
        'methods'             => 'POST',
        'callback'            => 'aichat_livechat_slack_events',
        'permission_callback' => '__return_true',
    ] );
    
    // Telegram webhook for receiving updates
    register_rest_route( 'aichat/v1', '/livechat/telegram', [
        'methods'             => 'POST',
        'callback'            => 'aichat_livechat_telegram_webhook',
        'permission_callback' => '__return_true', // Verified via Telegram secret
    ] );
    
    // Generic webhook for other integrations
    register_rest_route( 'aichat/v1', '/livechat/webhook', [
        'methods'             => 'POST',
        'callback'            => 'aichat_livechat_generic_webhook',
        'permission_callback' => 'aichat_livechat_verify_webhook_key',
    ] );
}

/**
 * Verify webhook key
 */
function aichat_livechat_verify_webhook_key( $request ) {
    $key = $request->get_header( 'X-Webhook-Key' );
    $stored_key = get_option( 'aichat_livechat_webhook_key' );
    
    if ( ! $stored_key ) {
        // Generate one if doesn't exist
        $stored_key = wp_generate_password( 32, false );
        update_option( 'aichat_livechat_webhook_key', $stored_key );
    }
    
    return $key === $stored_key;
}

/**
 * Handle Slack interactive webhooks (button clicks, modals)
 */
function aichat_livechat_slack_webhook( $request ) {
    // Log raw body for debugging
    $raw_body = $request->get_body();
    aichat_log_debug( '[LiveChat] Slack webhook raw body length: ' . strlen( $raw_body ) );
    
    // Slack sends payload as form-urlencoded with a "payload" parameter containing JSON
    $payload = $request->get_param( 'payload' );
    
    if ( $payload ) {
        // Decode the JSON from the payload parameter
        $payload = json_decode( $payload, true );
        aichat_log_debug( '[LiveChat] Slack payload decoded from param | json_error=' . json_last_error_msg() );
    }
    
    // Fallback: try parsing body params directly
    if ( empty( $payload ) ) {
        $body_params = $request->get_body_params();
        if ( isset( $body_params['payload'] ) ) {
            $payload = json_decode( $body_params['payload'], true );
            aichat_log_debug( '[LiveChat] Slack payload decoded from body_params | json_error=' . json_last_error_msg() );
        }
    }
    
    // Last fallback: JSON body
    if ( empty( $payload ) ) {
        $payload = $request->get_json_params();
        aichat_log_debug( '[LiveChat] Slack payload from JSON params' );
    }
    
    $type = $payload['type'] ?? 'unknown';
    aichat_log_debug( '[LiveChat] Slack webhook received | type=' . $type . ' | keys=' . implode( ',', array_keys( $payload ?: [] ) ) );
    
    if ( empty( $payload ) ) {
        aichat_log_debug( '[LiveChat] Slack webhook ERROR - empty payload' );
        return new WP_REST_Response( [ 'error' => 'Invalid payload' ], 400 );
    }
    
    // Handle different Slack interaction types
    $type = $payload['type'] ?? '';
    
    switch ( $type ) {
        case 'block_actions':
            return aichat_livechat_handle_slack_action( $payload );
            
        case 'view_submission':
            return aichat_livechat_handle_slack_modal( $payload );
            
        case 'url_verification':
            // Slack URL verification challenge
            return new WP_REST_Response( [ 'challenge' => $payload['challenge'] ?? '' ] );
            
        default:
            aichat_log_debug( '[LiveChat] Slack webhook unknown type: ' . $type );
            return new WP_REST_Response( [ 'ok' => true ] );
    }
}

/**
 * Handle Slack block actions (button clicks)
 */
function aichat_livechat_handle_slack_action( $payload ) {
    $actions = $payload['actions'] ?? [];
    $user = $payload['user'] ?? [];
    $trigger_id = $payload['trigger_id'] ?? '';
    $response_url = $payload['response_url'] ?? '';
    
    foreach ( $actions as $action ) {
        $action_id = $action['action_id'] ?? '';
        $session_id = $action['value'] ?? '';
        
        aichat_log_debug( "[LiveChat] Slack action | action_id={$action_id} session_id={$session_id}" );
        
        if ( $action_id === 'take_session' && $session_id ) {
            // Find or create a WP user for this Slack user
            $agent_id = aichat_livechat_get_or_create_slack_agent( $user );
            $agent_name = $user['name'] ?? $user['username'] ?? 'Slack Agent';
            
            if ( $agent_id ) {
                AIChat_LiveChat_Manager::agent_take_session( $session_id, $agent_id );
                
                // Update the original Slack message
                if ( $response_url ) {
                    wp_remote_post( $response_url, [
                        'headers' => [ 'Content-Type' => 'application/json' ],
                        'body'    => wp_json_encode( [
                            'replace_original' => true,
                            'text' => "✅ Chat taken by {$agent_name}",
                            'blocks' => [
                                [
                                    'type' => 'section',
                                    'text' => [
                                        'type' => 'mrkdwn',
                                        'text' => "✅ *Chat Taken*\n\n*Agent:* {$agent_name}\n*Session:* `{$session_id}`",
                                    ],
                                ],
                                [
                                    'type' => 'actions',
                                    'elements' => [
                                        [
                                            'type'      => 'button',
                                            'action_id' => 'quick_reply',
                                            'text'      => [ 'type' => 'plain_text', 'text' => '💬 Reply', 'emoji' => true ],
                                            'value'     => $session_id,
                                            'style'     => 'primary',
                                        ],
                                        [
                                            'type'      => 'button',
                                            'action_id' => 'close_session',
                                            'text'      => [ 'type' => 'plain_text', 'text' => '🤖 Return to Bot', 'emoji' => true ],
                                            'value'     => $session_id,
                                        ],
                                    ],
                                ],
                            ],
                        ] ),
                    ] );
                }
                
                return new WP_REST_Response( null, 200 );
            }
        }
        
        if ( $action_id === 'quick_reply' && $session_id && $trigger_id ) {
            // Open modal for quick reply
            $slack_token = get_option( 'aichat_livechat_slack_bot_token', '' );
            
            if ( ! empty( $slack_token ) ) {
                $modal = [
                    'trigger_id' => $trigger_id,
                    'view' => [
                        'type' => 'modal',
                        'callback_id' => 'livechat_reply',
                        'private_metadata' => wp_json_encode( [ 
                            'session_id' => $session_id,
                            'response_url' => $response_url,
                        ] ),
                        'title' => [
                            'type' => 'plain_text',
                            'text' => 'Reply to Visitor',
                        ],
                        'submit' => [
                            'type' => 'plain_text',
                            'text' => 'Send',
                        ],
                        'close' => [
                            'type' => 'plain_text',
                            'text' => 'Cancel',
                        ],
                        'blocks' => [
                            [
                                'type' => 'input',
                                'block_id' => 'message_block',
                                'element' => [
                                    'type' => 'plain_text_input',
                                    'action_id' => 'message_input',
                                    'multiline' => true,
                                    'placeholder' => [
                                        'type' => 'plain_text',
                                        'text' => 'Type your reply to the visitor...',
                                    ],
                                ],
                                'label' => [
                                    'type' => 'plain_text',
                                    'text' => 'Message',
                                ],
                            ],
                        ],
                    ],
                ];
                
                $response = wp_remote_post( 'https://slack.com/api/views.open', [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $slack_token,
                    ],
                    'body' => wp_json_encode( $modal ),
                ] );
                
                $body = json_decode( wp_remote_retrieve_body( $response ), true );
                aichat_log_debug( '[LiveChat] Slack modal open response: ' . wp_json_encode( $body ) );
            } else {
                aichat_log_debug( '[LiveChat] Slack bot token not configured - cannot open modal' );
            }
            
            return new WP_REST_Response( null, 200 );
        }
        
        if ( $action_id === 'close_session' && $session_id ) {
            // Return to bot instead of just closing (same behavior as dashboard)
            AIChat_LiveChat_Manager::return_to_bot( $session_id );
            
            if ( $response_url ) {
                wp_remote_post( $response_url, [
                    'headers' => [ 'Content-Type' => 'application/json' ],
                    'body'    => wp_json_encode( [
                        'replace_original' => true,
                        'text' => "🤖 Returned to Bot",
                        'blocks' => [
                            [
                                'type' => 'section',
                                'text' => [
                                    'type' => 'mrkdwn',
                                    'text' => "🤖 *Returned to Bot*\n\n*Session:* `{$session_id}`\n*By:* " . ( $user['name'] ?? 'Agent' ) . "\n\nThe visitor is now chatting with the AI assistant.",
                                ],
                            ],
                        ],
                    ] ),
                ] );
            }
            
            return new WP_REST_Response( null, 200 );
        }
    }
    
    return new WP_REST_Response( [ 'ok' => true ] );
}

/**
 * Handle Slack modal submissions
 */
function aichat_livechat_handle_slack_modal( $payload ) {
    $callback_id = $payload['view']['callback_id'] ?? '';
    $user = $payload['user'] ?? [];
    
    aichat_log_debug( "[LiveChat] Slack modal submission | callback_id={$callback_id}" );
    
    if ( $callback_id !== 'livechat_reply' ) {
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    // Get session_id from private_metadata
    $metadata = json_decode( $payload['view']['private_metadata'] ?? '{}', true );
    $session_id = $metadata['session_id'] ?? '';
    $response_url = $metadata['response_url'] ?? '';
    
    // Get the message from the modal
    $values = $payload['view']['state']['values'] ?? [];
    $message = $values['message_block']['message_input']['value'] ?? '';
    
    if ( empty( $session_id ) || empty( $message ) ) {
        aichat_log_debug( '[LiveChat] Slack modal - missing session_id or message' );
        return new WP_REST_Response( [
            'response_action' => 'errors',
            'errors' => [
                'message_block' => 'Please enter a message',
            ],
        ] );
    }
    
    // Get agent info
    $agent_name = $user['name'] ?? $user['username'] ?? 'Slack Agent';
    $agent_id = aichat_livechat_get_or_create_slack_agent( $user );
    
    // Ensure session is connected (auto-take if pending)
    $session_status = AIChat_LiveChat_Manager::get_session_status( $session_id );
    if ( $session_status && $session_status['status'] === 'pending' ) {
        AIChat_LiveChat_Manager::agent_take_session( $session_id, $agent_id );
    }
    
    // Add the message
    AIChat_LiveChat_Manager::add_message( $session_id, 'agent', $agent_name, $message );
    
    aichat_log_debug( "[LiveChat] Slack message sent | session={$session_id} agent={$agent_name} msg_length=" . strlen( $message ) );
    
    // Update original Slack message to confirm
    if ( $response_url ) {
        wp_remote_post( $response_url, [
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'response_type' => 'in_channel',
                'replace_original' => false,
                'text' => "💬 *Reply sent by {$agent_name}:*\n>{$message}",
            ] ),
        ] );
    }
    
    return new WP_REST_Response( [ 'response_action' => 'clear' ] );
}

/**
 * Handle Slack events (messages in threads)
 */
function aichat_livechat_slack_events( $request ) {
    $payload = $request->get_json_params();
    
    // URL verification
    if ( isset( $payload['type'] ) && $payload['type'] === 'url_verification' ) {
        return new WP_REST_Response( [ 'challenge' => $payload['challenge'] ?? '' ] );
    }
    
    // Handle message events
    $event = $payload['event'] ?? [];
    
    if ( $event['type'] === 'message' && ! empty( $event['thread_ts'] ) ) {
        // This is a reply in a thread - route to the session
        $thread_ts = $event['thread_ts'];
        $message = $event['text'] ?? '';
        $slack_user = $event['user'] ?? '';
        
        // Find session by thread timestamp (stored in metadata)
        $session = aichat_livechat_find_session_by_thread( $thread_ts );
        
        if ( $session && $message ) {
            $agent_name = aichat_livechat_get_slack_user_name( $slack_user );
            AIChat_LiveChat_Manager::add_message( $session->session_id, 'agent', $agent_name, $message );
        }
    }
    
    return new WP_REST_Response( [ 'ok' => true ] );
}

/**
 * Generic webhook for sending messages from external systems
 */
function aichat_livechat_generic_webhook( $request ) {
    $params = $request->get_json_params();
    
    $session_id = $params['session_id'] ?? '';
    $message = $params['message'] ?? '';
    $agent_name = $params['agent_name'] ?? 'External Agent';
    
    if ( ! $session_id || ! $message ) {
        return new WP_REST_Response( [ 'error' => 'session_id and message required' ], 400 );
    }
    
    global $wpdb;
    
    // Insert agent message directly
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
    $wpdb->insert(
        $wpdb->prefix . 'aichat_livechat_messages',
        [
            'session_id'  => $session_id,
            'sender_type' => 'agent',
            'sender_name' => $agent_name,
            'message'     => $message,
        ]
    );
    
    return new WP_REST_Response( [ 'success' => true, 'message_id' => $wpdb->insert_id ] );
}

/**
 * Helper: Get or create WP user for Slack user
 */
function aichat_livechat_get_or_create_slack_agent( $slack_user ) {
    $slack_id = $slack_user['id'] ?? '';
    $slack_name = $slack_user['name'] ?? 'Slack Agent';
    
    if ( ! $slack_id ) {
        return 0;
    }
    
    // Check if we have a mapping
    $user_id = get_option( 'aichat_slack_user_' . $slack_id );
    
    if ( $user_id && get_userdata( $user_id ) ) {
        return $user_id;
    }
    
    // Use the first admin as fallback
    $admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
    
    if ( ! empty( $admins ) ) {
        $user_id = $admins[0]->ID;
        update_option( 'aichat_slack_user_' . $slack_id, $user_id );
        return $user_id;
    }
    
    return 0;
}

/**
 * Helper: Find session by Slack thread timestamp
 * Note: This feature requires adding slack_thread_ts column to sessions table
 */
function aichat_livechat_find_session_by_thread( $thread_ts ) {
    global $wpdb;
    
    $table = AIChat_LiveChat_Manager::table_sessions();
    
    // TODO: Add slack_thread_ts column to sessions table for Slack integration
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    return $wpdb->get_row( $wpdb->prepare(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
        "SELECT * FROM {$table} WHERE session_id = %s",
        $thread_ts
    ) );
}

/**
 * Helper: Get Slack user display name
 */
function aichat_livechat_get_slack_user_name( $slack_user_id ) {
    // In a full implementation, this would call Slack API to get user info
    return 'Slack Agent';
}

/**
 * =========================================================================
 * TELEGRAM INTEGRATION
 * =========================================================================
 */

/**
 * Handle Telegram webhook updates
 */
function aichat_livechat_telegram_webhook( $request ) {
    $payload = $request->get_json_params();
    
    aichat_log_debug( '[LiveChat] Telegram webhook received | keys=' . implode( ',', array_keys( $payload ?: [] ) ) );
    
    if ( empty( $payload ) ) {
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    // Handle callback queries (button clicks)
    if ( isset( $payload['callback_query'] ) ) {
        return aichat_livechat_handle_telegram_callback( $payload['callback_query'] );
    }
    
    // Handle messages (replies from agent)
    if ( isset( $payload['message'] ) ) {
        return aichat_livechat_handle_telegram_message( $payload['message'] );
    }
    
    return new WP_REST_Response( [ 'ok' => true ] );
}

/**
 * Handle Telegram callback queries (button clicks)
 */
function aichat_livechat_handle_telegram_callback( $callback ) {
    $callback_id = $callback['id'] ?? '';
    $data = $callback['data'] ?? '';
    $from = $callback['from'] ?? [];
    $message = $callback['message'] ?? [];
    $chat_id = $message['chat']['id'] ?? '';
    $message_id = $message['message_id'] ?? '';
    
    aichat_log_debug( "[LiveChat] Telegram callback | data={$data} from_id={$from['id']}" );
    
    // Parse callback data (format: action:session_id)
    $parts = explode( ':', $data, 2 );
    $action = $parts[0] ?? '';
    $session_id = $parts[1] ?? '';
    
    if ( empty( $session_id ) ) {
        aichat_livechat_telegram_answer_callback( $callback_id, 'Invalid session' );
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    $agent_name = trim( ( $from['first_name'] ?? '' ) . ' ' . ( $from['last_name'] ?? '' ) ) ?: 'Telegram Agent';
    $agent_id = aichat_livechat_get_or_create_telegram_agent( $from );
    
    switch ( $action ) {
        case 'take':
            // Take the session
            AIChat_LiveChat_Manager::agent_take_session( $session_id, $agent_id );
            
            // Update the message
            aichat_livechat_telegram_edit_message( $chat_id, $message_id, 
                "✅ *Chat Taken*\n\n*Agent:* {$agent_name}\n*Session:* `{$session_id}`\n\n_Reply to this message to chat with the visitor._",
                [
                    [
                        [ 'text' => '🤖 Return to Bot', 'callback_data' => 'close:' . $session_id ],
                    ],
                ]
            );
            
            // Store mapping for replies
            update_option( 'aichat_telegram_session_' . $message_id, $session_id );
            update_option( 'aichat_telegram_chat_' . $session_id, $chat_id );
            update_option( 'aichat_telegram_msg_' . $session_id, $message_id );
            
            aichat_livechat_telegram_answer_callback( $callback_id, '✅ Chat taken! Reply to this message to respond.' );
            break;
            
        case 'close':
            // Return to bot
            AIChat_LiveChat_Manager::return_to_bot( $session_id );
            
            // Update the message
            aichat_livechat_telegram_edit_message( $chat_id, $message_id, 
                "🤖 *Returned to Bot*\n\n*Session:* `{$session_id}`\n*By:* {$agent_name}\n\n_The visitor is now chatting with the AI assistant._",
                []
            );
            
            aichat_livechat_telegram_answer_callback( $callback_id, '🤖 Visitor returned to bot' );
            break;
            
        default:
            aichat_livechat_telegram_answer_callback( $callback_id, 'Unknown action' );
    }
    
    return new WP_REST_Response( [ 'ok' => true ] );
}

/**
 * Handle Telegram messages (agent replies)
 */
function aichat_livechat_handle_telegram_message( $message ) {
    $chat_id = $message['chat']['id'] ?? '';
    $text = $message['text'] ?? '';
    $from = $message['from'] ?? [];
    $reply_to = $message['reply_to_message'] ?? null;
    
    // Only process if it's a reply to a session message
    if ( ! $reply_to || empty( $text ) ) {
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    $reply_message_id = $reply_to['message_id'] ?? '';
    $session_id = get_option( 'aichat_telegram_session_' . $reply_message_id, '' );
    
    if ( empty( $session_id ) ) {
        aichat_log_debug( "[LiveChat] Telegram reply - no session found for message_id={$reply_message_id}" );
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    // Verify session is connected
    $status = AIChat_LiveChat_Manager::get_session_status( $session_id );
    if ( ! $status || $status['status'] !== 'connected' ) {
        aichat_livechat_telegram_send_message( $chat_id, "⚠️ This session is no longer active.", null );
        return new WP_REST_Response( [ 'ok' => true ] );
    }
    
    // Get agent info
    $agent_name = trim( ( $from['first_name'] ?? '' ) . ' ' . ( $from['last_name'] ?? '' ) ) ?: 'Telegram Agent';
    
    // Add message to session
    AIChat_LiveChat_Manager::add_message( $session_id, 'agent', $agent_name, $text );
    
    aichat_log_debug( "[LiveChat] Telegram reply sent | session={$session_id} agent={$agent_name} text_len=" . strlen( $text ) );
    
    // Confirm with a reaction or reply
    aichat_livechat_telegram_send_message( $chat_id, "✓ _Sent to visitor_", $message['message_id'] );
    
    return new WP_REST_Response( [ 'ok' => true ] );
}

/**
 * Answer a Telegram callback query
 */
function aichat_livechat_telegram_answer_callback( $callback_id, $text ) {
    $bot_token = get_option( 'aichat_livechat_telegram_bot_token', '' );
    if ( empty( $bot_token ) ) {
        return;
    }
    
    wp_remote_post( "https://api.telegram.org/bot{$bot_token}/answerCallbackQuery", [
        'body' => [
            'callback_query_id' => $callback_id,
            'text'              => $text,
            'show_alert'        => false,
        ],
    ] );
}

/**
 * Edit a Telegram message
 */
function aichat_livechat_telegram_edit_message( $chat_id, $message_id, $text, $buttons = [] ) {
    $bot_token = get_option( 'aichat_livechat_telegram_bot_token', '' );
    if ( empty( $bot_token ) ) {
        return;
    }
    
    $data = [
        'chat_id'    => $chat_id,
        'message_id' => $message_id,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    
    if ( ! empty( $buttons ) ) {
        $data['reply_markup'] = wp_json_encode( [ 'inline_keyboard' => $buttons ] );
    } else {
        $data['reply_markup'] = wp_json_encode( [ 'inline_keyboard' => [] ] );
    }
    
    wp_remote_post( "https://api.telegram.org/bot{$bot_token}/editMessageText", [
        'body' => $data,
    ] );
}

/**
 * Send a Telegram message
 */
function aichat_livechat_telegram_send_message( $chat_id, $text, $reply_to_message_id = null ) {
    $bot_token = get_option( 'aichat_livechat_telegram_bot_token', '' );
    if ( empty( $bot_token ) ) {
        return;
    }
    
    $data = [
        'chat_id'    => $chat_id,
        'text'       => $text,
        'parse_mode' => 'Markdown',
    ];
    
    if ( $reply_to_message_id ) {
        $data['reply_to_message_id'] = $reply_to_message_id;
    }
    
    wp_remote_post( "https://api.telegram.org/bot{$bot_token}/sendMessage", [
        'body' => $data,
    ] );
}

/**
 * Helper: Get or create WP user for Telegram user
 */
function aichat_livechat_get_or_create_telegram_agent( $telegram_user ) {
    $telegram_id = $telegram_user['id'] ?? '';
    
    if ( ! $telegram_id ) {
        return 0;
    }
    
    // Check if we have a mapping
    $user_id = get_option( 'aichat_telegram_user_' . $telegram_id );
    
    if ( $user_id && get_userdata( $user_id ) ) {
        return $user_id;
    }
    
    // Use the first admin as fallback
    $admins = get_users( [ 'role' => 'administrator', 'number' => 1 ] );
    
    if ( ! empty( $admins ) ) {
        $user_id = $admins[0]->ID;
        update_option( 'aichat_telegram_user_' . $telegram_id, $user_id );
        return $user_id;
    }
    
    return 0;
}
