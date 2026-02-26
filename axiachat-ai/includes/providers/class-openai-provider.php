<?php
/**
 * Adapter para OpenAI API
 * 
 * Refactorización del código existente en class-aichat-ajax.php::call_openai_chat()
 * Mantiene la lógica exacta para garantizar backward compatibility.
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Load trait for tool execution
require_once dirname(__FILE__) . '/../traits/trait-aichat-tool-execution.php';

class AIChat_OpenAI_Provider implements AIChat_Provider_Interface {
    
    // Usar trait para ejecución de tools
    use AIChat_Tool_Execution;
    
    /**
     * Configuración del proveedor
     * @var array
     */
    protected $config = [];
    
    /**
     * Constructor
     * 
     * @param array $config Configuración ['api_key' => string, 'organization' => string (opcional)]
     */
    public function __construct( $config = [] ) {
        $this->config = $config;
    }
    
    /**
     * Obtener ID del proveedor
     * 
     * @return string
     */
    public function get_id() {
        return 'openai';
    }
    
    /**
     * Llamada principal al modelo (auto-router)
     * 
     * Router que detecta el modelo y selecciona la API adecuada:
     * - GPT-5* models → Responses API
     * - Otros models → Chat Completions API
     * 
     * Si hay tools, delega a métodos multi-ronda.
     * 
     * @param array $messages Array de mensajes en formato OpenAI
     * @param array $params Parámetros de la llamada
     * @return array Respuesta normalizada o error
     */
    public function chat( $messages, $params = [] ) {
        // Extraer configuración
        $api_key = $this->config['api_key'] ?? '';
        if ( empty( $api_key ) ) {
            $agency_enabled = ( function_exists('aichat_agency_is_configured') && aichat_agency_is_configured() )
                || (bool) get_option( 'aichat_agency_enabled', false );
            if ( ! $agency_enabled ) {
                return [ 'error' => __( 'Missing OpenAI API Key', 'axiachat-ai' ) ];
            }
            // Use placeholder when proxy handles auth
            $api_key = 'proxy';
            $this->config['api_key'] = $api_key;
        }
        
        // Extraer parámetros
        $model = $params['model'] ?? aichat_get_default_model('openai');
        $tools = $params['tools'] ?? null;
        $has_tools = !empty($tools) && is_array($tools);
        
        // Detectar si es modelo GPT-5 (Responses API)
        if ( $this->is_gpt5_model( $model ) ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[OpenAI Provider] Routing to Responses API (GPT-5 model)', ['model' => $model, 'has_tools' => $has_tools], true);
            }
            
            // ALWAYS route to chat_responses_with_tools for GPT-5 models
            // This allows filters (like aichat_openai_responses_tools) to inject server-side tools like web_search
            // The method will apply filters and then decide whether to use tools or fallback to simple chat
            return $this->chat_responses_with_tools( $messages, $params );
        }
        
        // GPT-4, GPT-3.5, O1, etc. → Chat Completions
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[OpenAI Provider] Routing to Chat Completions API', ['model' => $model, 'has_tools' => $has_tools], true);
        }
        
        if ( $has_tools ) {
            // Multi-ronda con tools
            return $this->chat_completions_with_tools( $messages, $params );
        }
        
        // Sin tools, llamada simple
        return $this->chat_completions( $messages, $params );
    }
    
    /**
     * Chat Completions API (GPT-4, GPT-3.5, O1, etc.)
     * 
     * CÓDIGO REFACTORIZADO DE: class-aichat-ajax.php::call_openai_chat()
     * 
     * @param array $messages Array de mensajes
     * @param array $params Parámetros
     * @return array Respuesta normalizada
     */
    protected function chat_completions( $messages, $params = [] ) {
        $api_key = $this->config['api_key'] ?? '';
        $model = $params['model'] ?? aichat_get_default_model('openai');
        $temperature = $params['temperature'] ?? 0.7;
        $max_tokens = $params['max_tokens'] ?? 2048;
        $tools = $params['tools'] ?? null;
        
        $endpoint = 'https://api.openai.com/v1/chat/completions';

        $payload = [
            'model'       => $model,
            'messages'    => array_values( $messages ),
            'temperature' => $temperature,
            'max_tokens'  => $max_tokens,
        ];
        
        // Añadir tools si están presentes
        if ( ! empty( $tools ) ) {
            $payload['tools'] = $tools;
        }

        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ];
        
        // Añadir organization header si está configurado
        if ( ! empty( $this->config['organization'] ) ) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }

        $res = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'timeout' => 45,
            'body'    => wp_json_encode( $payload ),
        ] );

        if ( is_wp_error( $res ) ) {
            return [ 'error' => $res->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );

        if ( $code >= 400 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI error.', 'axiachat-ai' );
            return [ 'error' => $msg ];
        }

        $text = $body['choices'][0]['message']['content'] ?? '';
        if ( $text === '' ) {
            return [ 'error' => __( 'Empty response from OpenAI.', 'axiachat-ai' ) ];
        }

        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            if ( isset($body['usage']) ) {
                aichat_log_debug('OpenAI chat usage', ['usage'=>$body['usage'], 'model'=>$model], true);
            } else {
                aichat_log_debug('OpenAI chat no usage field', ['model'=>$model], true);
            }
        }

        $usage = [];
        if ( isset($body['usage']) ) {
            $u = $body['usage'];
            $prompt_tokens = isset($u['prompt_tokens']) ? (int)$u['prompt_tokens'] : ( isset($u['input_tokens']) ? (int)$u['input_tokens'] : null );
            $completion_tokens = isset($u['completion_tokens']) ? (int)$u['completion_tokens'] : ( isset($u['output_tokens']) ? (int)$u['output_tokens'] : null );
            $total_tokens = isset($u['total_tokens']) ? (int)$u['total_tokens'] : null;
            if ( $total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null ) {
                $total_tokens = $prompt_tokens + $completion_tokens;
            }
            $usage['prompt_tokens'] = $prompt_tokens;
            $usage['completion_tokens'] = $completion_tokens;
            $usage['total_tokens'] = $total_tokens;
        }
        
        // Extraer información adicional
        $tool_calls = $body['choices'][0]['message']['tool_calls'] ?? null;
        $finish_reason = $body['choices'][0]['finish_reason'] ?? 'stop';
        
        // Construir respuesta normalizada
        $result = [
            'message' => $text,
            'usage' => $usage,
            'finish_reason' => $finish_reason
        ];
        
        // Añadir tool_calls si existen
        if ( $tool_calls ) {
            $result['tool_calls'] = $tool_calls;
        }
        
        return $result;
    }
    
    /**
     * Chat Completions API con soporte multi-ronda de tools
     * 
     * Implementa el loop multi-ronda para function calling:
     * 1. Llamar al modelo con tools disponibles
     * 2. Si devuelve tool_calls, ejecutar y continuar
     * 3. Repetir hasta respuesta final o límite de rondas
     * 
     * @param array $messages Array de mensajes iniciales
     * @param array $params Parámetros de la llamada
     * @return array Respuesta normalizada
     */
    protected function chat_completions_with_tools( $messages, $params = [] ) {
        $api_key = $this->config['api_key'] ?? '';
        $model = $params['model'] ?? aichat_get_default_model('openai');
        $tools = $params['tools'] ?? [];
        
        // Configuración de multi-ronda
        $max_rounds = $this->get_max_rounds( $params );
        $round = 1;
        $acc_messages = $messages;
        
        // Contexto para callbacks y logging
        $context = [
            'request_uuid' => $params['request_uuid'] ?? wp_generate_uuid4(),
            'session_id' => $params['session_id'] ?? '',
            'bot_slug' => $params['bot_slug'] ?? '',
            'message' => $params['message'] ?? '',
        ];
        
        $result = null;
        $final_answer = '';
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            $uid = substr($context['request_uuid'], 0, 8);
            aichat_log_debug(
                "[OpenAI Provider][{$uid}] Starting multi-round loop | max_rounds={$max_rounds} | tools=" . count($tools),
                [],
                true
            );
        }
        
        while ( $round <= $max_rounds ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                $uid = substr($context['request_uuid'], 0, 8);
                aichat_log_debug(
                    "[OpenAI Provider][{$uid}] Round {$round}/{$max_rounds} | messages=" . count($acc_messages),
                    [],
                    true
                );
            }
            
            // Llamar a Chat Completions con tools
            $round_params = array_merge($params, ['tools' => $tools]);
            $result = $this->chat_completions( $acc_messages, $round_params );
            
            // Verificar errores
            if ( isset($result['error']) ) {
                return $result;
            }
            
            $raw_msg = (string) ($result['message'] ?? '');
            $has_tool_calls = !empty($result['tool_calls']);
            
            // Si no hay tool calls, hemos terminado
            if ( !$has_tool_calls ) {
                $final_answer = $raw_msg;
                break;
            }
            
            // Si alcanzamos el límite de rondas, devolver lo que tengamos
            if ( $round === $max_rounds ) {
                $final_answer = $raw_msg;
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    $uid = substr($context['request_uuid'], 0, 8);
                    aichat_log_debug(
                        "[OpenAI Provider][{$uid}] Reached max_rounds limit with pending tool calls",
                        [],
                        true
                    );
                }
                break;
            }
            
            // Ejecutar tools usando el trait
            $context['round'] = $round;
            $tool_outputs = $this->execute_registered_tools( $result['tool_calls'], $context );
            
            // Log en BD
            $this->log_tool_executions( $result['tool_calls'], $tool_outputs, $round, $context );
            
            // Construir próximos mensajes en formato OpenAI
            $acc_messages = $this->append_openai_tool_messages( $acc_messages, $result, $tool_outputs );
            
            $round++;
        }
        
        // Actualizar mensaje final en result
        if ( $final_answer === '' && is_array($result) ) {
            $final_answer = (string) ($result['message'] ?? '');
        }
        
        $result['message'] = $final_answer;
        
        return $result;
    }
    
    /**
     * Construir mensajes para próxima ronda (formato OpenAI)
     * 
     * Añade el mensaje assistant con tool_calls y los mensajes tool con outputs
     * 
     * @param array $messages Mensajes acumulados
     * @param array $result Resultado de la llamada anterior
     * @param array $tool_outputs Outputs ejecutados
     * @return array Mensajes actualizados
     */
    protected function append_openai_tool_messages( $messages, $result, $tool_outputs ) {
        // Construir mensaje assistant con tool_calls
        $assistant_msg = [
            'role' => 'assistant',
            'content' => $result['message'] ?? '',
        ];
        
        // Añadir tool_calls en formato OpenAI
        $assistant_tool_calls = [];
        foreach ( $result['tool_calls'] as $tc ) {
            $assistant_tool_calls[] = [
                'id' => $tc['id'],
                'type' => 'function',
                'function' => [
                    'name' => $tc['function']['name'] ?? $tc['name'] ?? '',
                    'arguments' => $tc['function']['arguments'] ?? $tc['arguments'] ?? '{}',
                ],
            ];
        }
        
        if ( $assistant_tool_calls ) {
            $assistant_msg['tool_calls'] = $assistant_tool_calls;
        }
        
        // Construir mensajes tool con outputs
        $tool_messages = [];
        foreach ( $tool_outputs as $output ) {
            $tool_messages[] = [
                'role' => 'tool',
                'tool_call_id' => $output['tool_call_id'],
                'content' => $output['output'],
            ];
        }
        
        // Acumular: mensajes anteriores + assistant + tools
        return array_merge( $messages, [ $assistant_msg ], $tool_messages );
    }
    
    /**
     * Responses API (GPT-5 models)
     * 
     * Implementación simplificada sin soporte multi-ronda de tools.
     * Para feature completa, ver legacy code in class-aichat-ajax.php::call_openai_responses()
     * 
     * @param array $messages Array de mensajes
     * @param array $params Parámetros
     * @return array Respuesta normalizada
     */
    protected function chat_responses( $messages, $params = [] ) {
        $api_key = $this->config['api_key'] ?? '';
        $model = $params['model'] ?? aichat_get_default_model('openai');
        $max_tokens = $params['max_tokens'] ?? 2048;
        
        $endpoint = 'https://api.openai.com/v1/responses';
        
        // Convertir mensajes a formato Responses API (multi-turno estructurado)
        list( $instructions_field, $input_items ) = $this->convert_messages_to_responses_format( $messages );
        
        // Construir payload para Responses API
        $payload = [
            'model' => $model,
            'instructions' => $instructions_field,
            'input' => $input_items,
            'max_output_tokens' => (int) $max_tokens,
        ];
        
        // GPT-5 models NO aceptan temperature según la API
        // (temperature es ignorado en Responses API actualmente)
        
        $headers = [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ];
        
        if ( ! empty( $this->config['organization'] ) ) {
            $headers['OpenAI-Organization'] = $this->config['organization'];
        }
        
        $res = wp_remote_post( $endpoint, [
            'headers' => $headers,
            'timeout' => 45,
            'body'    => wp_json_encode( $payload ),
        ] );
        
        if ( is_wp_error( $res ) ) {
            return [ 'error' => $res->get_error_message() ];
        }
        
        $code = wp_remote_retrieve_response_code( $res );
        $body = json_decode( wp_remote_retrieve_body( $res ), true );
        
        if ( $code >= 400 ) {
            $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI Responses API error.', 'axiachat-ai' );
            return [ 'error' => $msg ];
        }
        
        // Responses API estructura diferente
        $text = '';
        if ( isset( $body['output'] ) && is_array( $body['output'] ) ) {
            foreach ( $body['output'] as $output_item ) {
                if ( isset( $output_item['type'] ) && $output_item['type'] === 'message' ) {
                    if ( isset( $output_item['content'] ) && is_array( $output_item['content'] ) ) {
                        foreach ( $output_item['content'] as $content_item ) {
                            if ( isset( $content_item['type'] ) && $content_item['type'] === 'output_text' ) {
                                $text .= $content_item['text'] ?? '';
                            }
                        }
                    }
                }
            }
        }
        
        if ( $text === '' ) {
            return [ 'error' => __( 'Empty response from OpenAI Responses API.', 'axiachat-ai' ) ];
        }

        // Parsear usage si viene en Responses API
        $usage = [];
        if ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) {
            $u = $body['usage'];
            $prompt_tokens = isset($u['prompt_tokens']) ? (int)$u['prompt_tokens'] : ( isset($u['input_tokens']) ? (int)$u['input_tokens'] : null );
            $completion_tokens = isset($u['completion_tokens']) ? (int)$u['completion_tokens'] : ( isset($u['output_tokens']) ? (int)$u['output_tokens'] : null );
            $total_tokens = isset($u['total_tokens']) ? (int)$u['total_tokens'] : null;
            if ( $total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null ) {
                $total_tokens = $prompt_tokens + $completion_tokens;
            }
            if ( $prompt_tokens === null && $completion_tokens === null && $total_tokens !== null ) {
                $prompt_tokens = (int)$total_tokens;
                $completion_tokens = 0;
            }
            if ( $prompt_tokens !== null || $completion_tokens !== null || $total_tokens !== null ) {
                $usage = [
                    'prompt_tokens' => ($prompt_tokens !== null) ? (int)$prompt_tokens : 0,
                    'completion_tokens' => ($completion_tokens !== null) ? (int)$completion_tokens : 0,
                    'total_tokens' => ($total_tokens !== null) ? (int)$total_tokens : ( (int)($prompt_tokens ?? 0) + (int)($completion_tokens ?? 0) ),
                ];
            }
        }
        
        return [
            'message' => $text,
            'model'   => $body['model'] ?? $model,
            'usage'   => $usage,
        ];
    }
    
    /**
     * Llamada a Responses API con soporte multi-ronda para tools (GPT-5)
     * Implementa el patrón stateful con previous_response_id
     *
     * @param array $messages
     * @param array $params
     * @return array ['message'=>string, 'model'=>string] o ['error'=>string]
     */
    protected function chat_responses_with_tools( $messages, $params ) {
        $model = $params['model'] ?? aichat_get_default_model('openai');
        $max_tokens = $params['max_output_tokens'] ?? 2048;
        $tools = isset( $params['tools'] ) && is_array( $params['tools'] ) ? $params['tools'] : [];
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[OpenAI Provider] Before applying filter', [
                'tools_count' => count($tools),
                'bot_slug' => $params['bot_slug'] ?? null
            ], true);
        }
        
        // Apply provider-specific tools filter BEFORE checking if empty
        // This allows filters to inject server-side tools like web_search
        $ctx = [
            'model' => $model,
            'bot' => $params['bot_slug'] ?? null
        ];
        $tools = apply_filters( 'aichat_openai_responses_tools', $tools, $ctx );
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[OpenAI Provider] After applying filter', [
                'tools_count' => count($tools),
                'tools' => array_map(function($t) {
                    return [
                        'type' => $t['type'] ?? 'function',
                        'name' => $t['function']['name'] ?? $t['name'] ?? '?'
                    ];
                }, $tools)
            ], true);
        }
        
        // NOW check if empty after filter application
        if ( empty( $tools ) ) {
            return $this->chat_responses( $messages, $params );
        }
        
        // Convertir mensajes a formato Responses API (multi-turno estructurado)
        list( $instructions, $input_items ) = $this->convert_messages_to_responses_format( $messages );
        
        aichat_log_debug('[OpenAI Provider] Responses input prepared', [
            'turns'      => count($input_items),
            'tools'      => count($tools),
            'last_role'  => !empty($input_items) ? $input_items[count($input_items)-1]['role'] : '?',
        ], true);
        
        // Normalizar tools: Chat Completions format → Responses API format
        $normalized_tools = $this->normalize_tools_for_responses( $tools );
        
        // Variables para el loop multi-ronda
        $max_rounds = $this->get_max_rounds( $params );
        $round = 1;
        $response_id = null;
        $pending_tool_outputs = [];
        $final_text = '';
        $prompt_tokens_acc = 0;
        $completion_tokens_acc = 0;
        $total_tokens_acc = 0;
        $usage_seen = false;
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[OpenAI Provider] Responses multi-round START', [
                'model' => $model,
                'max_rounds' => $max_rounds,
                'tools_count' => count($normalized_tools)
            ], true);
        }
        
        while ( $round <= $max_rounds ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug("[OpenAI Provider] Responses round $round", [
                    'has_pending_outputs' => !empty($pending_tool_outputs)
                ], true);
            }
            
            // Construir payload según si es primera ronda o subsecuente
            if ( $response_id === null ) {
                // PRIMERA RONDA: enviar instrucciones, input multi-turno, tools
                $payload = [
                    'model'              => $model,
                    'instructions'       => $instructions,
                    'input'              => $input_items,
                    'max_output_tokens'  => $max_tokens,
                    'tools'              => $normalized_tools,
                    'tool_choice'        => 'auto',
                    'include'            => [ 'web_search_call.action.sources' ]
                ];
            } else {
                // RONDAS SUBSECUENTES: enviar previous_response_id + function_call_output
                $fco_items = [];
                foreach ( $pending_tool_outputs as $to ) {
                    $fco_items[] = [
                        'type'    => 'function_call_output',
                        'call_id' => $to['tool_call_id'],
                        'output'  => $to['output']
                    ];
                }
                
                // Fallback: si no hay outputs (ej: continuación por max_output_tokens), enviar message mínimo
                if ( empty( $fco_items ) ) {
                    $fco_items[] = [
                        'type' => 'message',
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_text',
                                'text' => '(continue)'
                            ]
                        ]
                    ];
                }
                
                $payload = [
                    'model'              => $model,
                    'previous_response_id' => $response_id,
                    'input'              => $fco_items,
                    'max_output_tokens'  => $max_tokens,
                    'include'            => [ 'web_search_call.action.sources' ]
                ];
            }
            
            // Llamada a la API
            $res = wp_remote_post(
                'https://api.openai.com/v1/responses',
                [
                    'headers' => [
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $this->config['api_key']
                    ],
                    'body'    => wp_json_encode( $payload ),
                    'timeout' => 60
                ]
            );
            
            if ( is_wp_error( $res ) ) {
                return [ 'error' => $res->get_error_message() ];
            }
            
            $code = wp_remote_retrieve_response_code( $res );
            $body = json_decode( wp_remote_retrieve_body( $res ), true );
            
            if ( $code >= 400 ) {
                $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI Responses API error.', 'axiachat-ai' );
                return [ 'error' => $msg ];
            }
            
            // Guardar response_id para siguiente ronda
            $response_id = $body['id'] ?? null;
            
            // Parsear output: buscar text y tool_calls
            $text = '';
            $tool_calls = [];
            $web_search_sources = [];
            
            if ( isset( $body['output'] ) && is_array( $body['output'] ) ) {
                foreach ( $body['output'] as $output_item ) {
                    $type = $output_item['type'] ?? '';
                    
                    if ( $type === 'message' ) {
                        // Extraer texto
                        if ( isset( $output_item['content'] ) && is_array( $output_item['content'] ) ) {
                            foreach ( $output_item['content'] as $content_item ) {
                                if ( isset( $content_item['type'] ) && $content_item['type'] === 'output_text' ) {
                                    $text .= $content_item['text'] ?? '';
                                }
                            }
                        }
                    } elseif ( $type === 'function_call' ) {
                        // Extraer llamada a tool
                        $tool_calls[] = [
                            'id'        => $output_item['call_id'] ?? '',
                            'name'      => $output_item['name'] ?? '',
                            'arguments' => $output_item['arguments'] ?? ''
                        ];
                    } elseif ( $type === 'web_search_call' ) {
                        // Extraer sources del web search
                        if ( isset( $output_item['action']['sources'] ) && is_array( $output_item['action']['sources'] ) ) {
                            foreach ( $output_item['action']['sources'] as $source ) {
                                $web_search_sources[] = [
                                    'url' => $source['url'] ?? '',
                                    'title' => $source['title'] ?? '',
                                    'snippet' => $source['snippet'] ?? ''
                                ];
                            }
                        }
                    }
                }
            }
            
            // Acumular texto final
            if ( $text !== '' ) {
                $final_text .= $text;
            }
            
            // Parsear usage (necesario para handshake tool_pending)
            $prompt_tokens = null;
            $completion_tokens = null;
            $total_tokens = null;
            
            if ( isset( $body['usage'] ) && is_array( $body['usage'] ) ) {
                $u = $body['usage'];
                $prompt_tokens = isset($u['prompt_tokens']) ? (int)$u['prompt_tokens'] : ( isset($u['input_tokens']) ? (int)$u['input_tokens'] : null );
                $completion_tokens = isset($u['completion_tokens']) ? (int)$u['completion_tokens'] : ( isset($u['output_tokens']) ? (int)$u['output_tokens'] : null );
                $total_tokens = isset($u['total_tokens']) ? (int)$u['total_tokens'] : null;
                
                if ( $total_tokens === null && $prompt_tokens !== null && $completion_tokens !== null ) {
                    $total_tokens = $prompt_tokens + $completion_tokens;
                }
            }

            // Acumular usage por ronda (para que se registren tokens/coste en conversación)
            if ( $prompt_tokens !== null || $completion_tokens !== null || $total_tokens !== null ) {
                $usage_seen = true;
                if ( $prompt_tokens === null && $completion_tokens === null && $total_tokens !== null ) {
                    // Si sólo llega total, asignarlo a prompt para no perder contabilidad
                    $prompt_tokens = (int)$total_tokens;
                    $completion_tokens = 0;
                }
                $pt = ($prompt_tokens !== null) ? (int)$prompt_tokens : 0;
                $ct = ($completion_tokens !== null) ? (int)$completion_tokens : 0;
                $tt = ($total_tokens !== null) ? (int)$total_tokens : ($pt + $ct);
                $prompt_tokens_acc += $pt;
                $completion_tokens_acc += $ct;
                $total_tokens_acc += $tt;
            }
            
            // Si no hay tool_calls, detectar function calls filtrados como texto (GPT-5.x bug)
            if ( empty( $tool_calls ) && $text !== '' && strpos($text, 'to=functions.') !== false ) {
                if ( preg_match_all('/to=functions\\.(\w+).*?\\{([^}]+)\\}/su', $text, $leaked_m, PREG_SET_ORDER) ) {
                    foreach ( $leaked_m as $lm ) {
                        $leaked_args_raw = '{' . $lm[2] . '}';
                        $leaked_test = json_decode($leaked_args_raw, true);
                        if ( is_array($leaked_test) && !empty($leaked_test) ) {
                            $tool_calls[] = [
                                'id'        => 'leaked_' . wp_generate_uuid4(),
                                'name'      => $lm[1],
                                'arguments' => $leaked_args_raw,
                            ];
                        }
                    }
                    if ( !empty($tool_calls) ) {
                        aichat_log_debug('[OpenAI Provider] Recovered ' . count($tool_calls) . ' leaked function_call(s) from text', [
                            'round' => $round,
                            'tools' => array_map(function($t){ return $t['name']; }, $tool_calls),
                        ], true);
                        // Limpiar texto garbled
                        $text = '';
                        $final_text = '';
                    }
                }
            }
            
            // Si no hay tool_calls, verificar si terminamos o continuamos
            if ( empty( $tool_calls ) ) {
                // Check incomplete status: si quedó incompleto por max_output_tokens, continuar
                $status = $body['status'] ?? '';
                $incomp_reason = isset($body['incomplete_details']['reason']) ? (string)$body['incomplete_details']['reason'] : '';
                
                if ( $status === 'incomplete' && $incomp_reason === 'max_output_tokens' && $round < $max_rounds ) {
                    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                        aichat_log_debug('[OpenAI Provider] Responses incomplete due to max_output_tokens - continuing', [
                            'round' => $round
                        ], true);
                    }
                    // Continuar a siguiente ronda sin tool outputs (solo para obtener más texto)
                    $pending_tool_outputs = [];
                    $round++;
                    continue;
                }
                
                // Respuesta final alcanzada
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[OpenAI Provider] Responses multi-round END (no tool_calls)', [
                        'round' => $round,
                        'final_text_length' => strlen($final_text),
                        'status' => $status
                    ], true);
                }
                break;
            }
            
            // HANDSHAKE tool_pending: en primera ronda con tool_calls, retornar sin ejecutar
            if ( $round === 1 ) {
                // Construir metadata de activity_label por tool
                $registered_tools = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
                $pending = [];
                foreach ( $tool_calls as $tc ) {
                    $fname = $tc['name'];
                    $activity = '';
                    if ( isset($registered_tools[$fname]['activity_label']) ) {
                        $activity = (string)$registered_tools[$fname]['activity_label'];
                    } else {
                        $activity = 'Running tool: '.$fname.'...';
                    }
                    $pending[] = [
                        'call_id' => $tc['id'],
                        'name' => $fname,
                        'args' => $tc['arguments'],
                        'activity_label' => $activity
                    ];
                }
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[OpenAI Provider] Responses tool_pending handshake', [
                        'response_id' => $response_id,
                        'tool_count' => count($pending)
                    ], true);
                }
                
                return [
                    'status' => 'tool_pending',
                    'response_id' => $response_id,
                    'tool_calls' => $pending,
                    'usage' => [
                        'prompt_tokens' => $prompt_tokens,
                        'completion_tokens' => $completion_tokens,
                        'total_tokens' => $total_tokens
                    ]
                ];
            }
            
            // Ejecutar tools usando el trait
            $context = [
                'bot_id'        => $params['bot_id'] ?? 0,
                'conversation_id' => $params['conversation_id'] ?? 0,
                'provider'      => 'openai'
            ];
            
            $outputs = $this->execute_registered_tools( $tool_calls, $context );
            
            // Log de ejecuciones
            $this->log_tool_executions( $tool_calls, $outputs, $round, $context );
            
            // Preparar pending_tool_outputs para siguiente ronda
            $pending_tool_outputs = [];
            foreach ( $outputs as $output ) {
                $pending_tool_outputs[] = [
                    'tool_call_id' => $output['tool_call_id'],
                    'output'       => $output['output']  // ← STRING del trait
                ];
            }
            
            $round++;
        }
        
        // Fin del loop
        if ( $round > $max_rounds ) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[OpenAI Provider] Responses multi-round STOPPED (max rounds)', [
                    'max_rounds' => $max_rounds
                ], true);
            }
        }
        
        // Sanitizar posibles tokens de function_call filtrados como texto
        $final_text = $this->sanitize_function_call_leak( $final_text );
        
        if ( $final_text === '' ) {
            return [ 'error' => __( 'Empty response from OpenAI Responses API after tool execution.', 'axiachat-ai' ) ];
        }
        
        // Log web_search sources si existen
        if ( ! empty( $web_search_sources ) && defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[OpenAI Provider] Web Search sources detected', [
                'source_count' => count($web_search_sources),
                'sources' => array_map(function($s) {
                    return [
                        'title' => $s['title'] ?? '',
                        'url' => $s['url'] ?? ''
                    ];
                }, $web_search_sources)
            ], true);
        }
        
        $usage = [];
        if ( $usage_seen ) {
            if ( $total_tokens_acc <= 0 && ( $prompt_tokens_acc > 0 || $completion_tokens_acc > 0 ) ) {
                $total_tokens_acc = $prompt_tokens_acc + $completion_tokens_acc;
            }
            $usage = [
                'prompt_tokens' => (int)$prompt_tokens_acc,
                'completion_tokens' => (int)$completion_tokens_acc,
                'total_tokens' => (int)$total_tokens_acc,
            ];
        }

        return [
            'message' => $final_text,
            'model'   => $body['model'] ?? $model,
            'usage'   => $usage,
        ];
    }
    
    /**
     * Normaliza tools de formato Chat Completions a formato Responses API
     *
     * @param array $tools
     * @return array
     */
    protected function normalize_tools_for_responses( $tools ) {
        $normalized = [];
        
        foreach ( $tools as $tool ) {
            $type = $tool['type'] ?? 'function';
            
            if ( $type === 'function' ) {
                $func = $tool['function'] ?? [];
                $normalized[] = [
                    'type'        => 'function',
                    'name'        => $func['name'] ?? '',
                    'description' => $func['description'] ?? '',
                    'parameters'  => $func['parameters'] ?? (object)[]
                ];
            } elseif ( $type === 'web_search' ) {
                // Web search tool - only include filters if present
                $ws_tool = [ 'type' => 'web_search' ];
                if ( isset( $tool['filters'] ) && ! empty( $tool['filters'] ) ) {
                    $ws_tool['filters'] = $tool['filters'];
                }
                $normalized[] = $ws_tool;
            }
        }
        
        return $normalized;
    }
    
    /**
     * Convierte mensajes formato Chat Completions a formato Responses API
     *
     * @param array $messages Chat Completions-style messages array
     * @return array [instructions, input_items] — instructions=string, input_items=array of Responses API conversation items
     */
    protected function convert_messages_to_responses_format( $messages ) {
        $instructions = '';
        $input_items = [];
        
        foreach ( $messages as $m ) {
            if ( ! is_array( $m ) ) {
                continue;
            }
            
            $role = $m['role'] ?? '';
            $content = is_string( $m['content'] ?? '' ) ? (string) $m['content'] : '';
            
            if ( $content === '' ) {
                continue;
            }
            
            if ( $role === 'system' ) {
                $instructions .= ( $instructions ? "\n\n" : '' ) . $content;
            } elseif ( $role === 'user' || $role === 'assistant' ) {
                $input_items[] = [
                    'role'    => $role,
                    'content' => $content,
                ];
            }
        }
        
        if ( $instructions === '' ) {
            $instructions = 'You are a helpful assistant.';
        }
        
        if ( empty( $input_items ) ) {
            $input_items[] = [ 'role' => 'user', 'content' => 'Hello' ];
        }
        
        return [ $instructions, $input_items ];
    }
    
    /**
     * Detectar y limpiar tokens internos de function calling que se filtran como texto.
     * GPT-5.x a veces emite "to=functions.TOOL" + caracteres CJK/Thai + JSON en vez de usar function_call.
     *
     * @param string $text Texto de respuesta del modelo
     * @return string Texto limpio, o vacío si todo era basura
     */
    protected function sanitize_function_call_leak( $text ) {
        if ( strpos( $text, 'to=functions.' ) === false ) {
            return $text;
        }
        aichat_log_debug( '[OpenAI Provider] Detected function_call token leak in output', [
            'text_len' => strlen( $text ),
            'preview'  => mb_substr( $text, 0, 300 ),
        ], true );
        // Eliminar bloques to=functions.XXX ... {json}
        $cleaned = preg_replace( '/to=functions\.\w+[^{}]*\{[^}]*\}/su', '', $text );
        // Eliminar fragmentos CJK/Thai residuales y caracteres de ancho completo
        $cleaned = preg_replace( '/[\x{4E00}-\x{9FFF}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}\x{0E00}-\x{0E7F}\x{3010}\x{3011}]+/u', '', $cleaned );
        $cleaned = preg_replace( '/\s{2,}/', ' ', trim( $cleaned ) );
        if ( mb_strlen( $cleaned ) < 10 ) {
            return '';
        }
        return $cleaned;
    }
    
    /**
     * Detectar si el modelo es GPT-5 o GPT-5.x (Responses API)
     * 
     * Incluye: gpt-5, gpt-5-mini, gpt-5-nano, gpt-5.2, gpt-5.2-chat-latest, gpt-5.2-codex-max, etc.
     * 
     * @param string $model Nombre del modelo
     * @return bool True si es GPT-5 o GPT-5.x
     */
    protected function is_gpt5_model( $model ) {
        return (bool) preg_match( '/^gpt-5(\.\d+)?([\b_-]|$)/i', (string) $model );
    }
    
    /**
     * Calcular coste de la llamada en microcents
     * 
     * Precios basados en documentación oficial de OpenAI (Nov 2025)
     * https://openai.com/pricing
     * 
     * @param array $usage Array con tokens: ['prompt_tokens', 'completion_tokens', 'total_tokens']
     * @param string $model ID del modelo usado
     * @return int|null Coste en microcents (1 cent = 10,000 micros), null si modelo desconocido
     */
    public function calculate_cost( $usage, $model ) {
        // Tabla de precios (USD per 1K tokens)
        $pricing = [
            // GPT-5.2 (Dec 2025) - Latest generation
            'gpt-5.2-chat-latest' => [
                'prompt' => 2.00,
                'completion' => 8.00
            ],
            'gpt-5.2' => [
                'prompt' => 3.00,
                'completion' => 12.00
            ],
            
            // GPT-5 (2025)
            'gpt-5' => [
                'prompt' => 2.50,
                'completion' => 10.00
            ],
            'gpt-5-mini' => [
                'prompt' => 0.30,
                'completion' => 1.20
            ],
            'gpt-5-nano' => [
                'prompt' => 0.10,
                'completion' => 0.40
            ],
            
            // GPT-4o (Optimized)
            'gpt-4o' => [
                'prompt' => 2.50,
                'completion' => 10.00
            ],
            'gpt-4o-2024-11-20' => [
                'prompt' => 2.50,
                'completion' => 10.00
            ],
            'gpt-4o-2024-08-06' => [
                'prompt' => 2.50,
                'completion' => 10.00
            ],
            'gpt-4o-2024-05-13' => [
                'prompt' => 5.00,
                'completion' => 15.00
            ],
            
            // GPT-4o mini
            'gpt-4o-mini' => [
                'prompt' => 0.15,
                'completion' => 0.60
            ],
            'gpt-4o-mini-2024-07-18' => [
                'prompt' => 0.15,
                'completion' => 0.60
            ],
            
            // O1 (Reasoning models)
            'o1-preview' => [
                'prompt' => 15.00,
                'completion' => 60.00
            ],
            'o1-preview-2024-09-12' => [
                'prompt' => 15.00,
                'completion' => 60.00
            ],
            'o1-mini' => [
                'prompt' => 3.00,
                'completion' => 12.00
            ],
            'o1-mini-2024-09-12' => [
                'prompt' => 3.00,
                'completion' => 12.00
            ],
            
            // GPT-4 Turbo
            'gpt-4-turbo' => [
                'prompt' => 10.00,
                'completion' => 30.00
            ],
            'gpt-4-turbo-2024-04-09' => [
                'prompt' => 10.00,
                'completion' => 30.00
            ],
            'gpt-4-turbo-preview' => [
                'prompt' => 10.00,
                'completion' => 30.00
            ],
            
            // GPT-4 (Legacy)
            'gpt-4' => [
                'prompt' => 30.00,
                'completion' => 60.00
            ],
            'gpt-4-0613' => [
                'prompt' => 30.00,
                'completion' => 60.00
            ],
            'gpt-4-32k' => [
                'prompt' => 60.00,
                'completion' => 120.00
            ],
            
            // GPT-3.5 Turbo
            'gpt-3.5-turbo' => [
                'prompt' => 0.50,
                'completion' => 1.50
            ],
            'gpt-3.5-turbo-0125' => [
                'prompt' => 0.50,
                'completion' => 1.50
            ],
            'gpt-3.5-turbo-1106' => [
                'prompt' => 1.00,
                'completion' => 2.00
            ],
            
            // Modelos antiguos (fallback)
            'text-davinci-003' => [
                'prompt' => 20.00,
                'completion' => 20.00
            ],
        ];
        
        // Verificar si el modelo existe en la tabla
        if ( ! isset( $pricing[ $model ] ) ) {
            // Modelo desconocido, no podemos calcular coste
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug( "[OpenAI Provider] Unknown model for cost calculation: {$model}", [], true );
            }
            return null;
        }
        
        $rates = $pricing[ $model ];
        $prompt_tokens = $usage['prompt_tokens'] ?? 0;
        $completion_tokens = $usage['completion_tokens'] ?? 0;
        
        // Calcular coste en USD
        $cost_usd = ( $prompt_tokens / 1000 * $rates['prompt'] ) + 
                    ( $completion_tokens / 1000 * $rates['completion'] );
        
        // Convertir a microcents
        // 1 USD = 100 cents = 1,000,000 microcents
        // 1 cent = 10,000 microcents
        $cost_microcents = (int) round( $cost_usd * 100 * 10000 );
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug( "[OpenAI Provider] Cost calculated", [
                'model' => $model,
                'prompt_tokens' => $prompt_tokens,
                'completion_tokens' => $completion_tokens,
                'cost_usd' => $cost_usd,
                'cost_microcents' => $cost_microcents
            ], true );
        }
        
        return $cost_microcents;
    }
}
