<?php
/**
 * Trait para ejecución de tools multi-ronda
 * 
 * Proporciona funcionalidad común para todos los providers que soportan tool calling:
 * - Ejecutar callbacks de tools registrados
 * - Logging en base de datos (wp_aichat_tool_calls)
 * - Límites de rondas configurables vía filter
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

trait AIChat_Tool_Execution {
    
    /**
     * Ejecutar tools registrados
     * 
     * Ejecuta los callbacks de tools disponibles en el sistema y retorna
     * los outputs normalizados para enviar al modelo.
     * 
     * @param array $tool_calls Tool calls extraídos del provider
     *                          Formato: [['id'=>'...', 'name'=>'...', 'arguments'=>'...']]
     * @param array $context Contexto de ejecución (session_id, bot_slug, message, round, etc.)
     * @return array Outputs normalizados con estructura:
     *               [['tool_call_id'=>'...', 'name'=>'...', 'arguments'=>'...', 'output'=>'...', 'elapsed_ms'=>123]]
     */
    protected function execute_registered_tools( $tool_calls, $context = [] ) {
        $registered = function_exists('aichat_get_registered_tools') 
            ? aichat_get_registered_tools() 
            : [];
        
        $outputs = [];
        
        foreach ( $tool_calls as $tc ) {
            $fname = $tc['name'] ?? '';
            $raw_args = $tc['arguments'] ?? '{}';
            
            // Normalizar argumentos a array
            $args = is_string($raw_args) ? json_decode($raw_args, true) : $raw_args;
            if ( !is_array($args) ) {
                $args = [];
            }
            
            $start = microtime(true);
            $output_str = '';
            
            if ( isset($registered[$fname]) && is_callable($registered[$fname]['callback']) ) {
                try {
                    // Preparar contexto para callback
                    $cb_context = array_merge($context, [
                        'question' => $context['message'] ?? '',
                        'round' => $context['round'] ?? 1,
                    ]);
                    
                    // Ejecutar callback
                    $result = call_user_func( $registered[$fname]['callback'], $args, $cb_context );
                    
                    // Normalizar output a string
                    if ( is_array($result) ) {
                        $output_str = wp_json_encode($result);
                    } elseif ( is_string($result) ) {
                        $output_str = $result;
                    } else {
                        $output_str = '"ok"';
                    }
                } catch ( \Throwable $e ) {
                    // Capturar excepciones y devolver error estructurado
                    $output_str = wp_json_encode([
                        'ok' => false,
                        'error' => 'exception',
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                // Tool no encontrado
                $output_str = wp_json_encode(['ok' => false, 'error' => 'unknown_tool']);
            }
            
            $elapsed_ms = round((microtime(true) - $start) * 1000);
            
            // Truncar outputs muy largos para evitar problemas con contexto
            if ( mb_strlen($output_str) > 4000 ) {
                $output_str = mb_substr($output_str, 0, 4000) . '…';
            }
            
            // Tool execution logging (always log — critical for diagnosing tool failures)
            aichat_log_debug(
                "[AIChat Tools] Executed: {$fname} | {$elapsed_ms}ms | args_len=" . strlen($raw_args) . " | output_len=" . strlen($output_str),
                [],
                true
            );
            
            $outputs[] = [
                'tool_call_id' => $tc['id'] ?? '',
                'name' => $fname,
                'arguments' => $raw_args,
                'output' => $output_str,
                'elapsed_ms' => $elapsed_ms,
            ];
        }
        
        return $outputs;
    }
    
    /**
     * Log tool executions en base de datos
     * 
     * Registra cada ejecución de tool en la tabla wp_aichat_tool_calls
     * para auditoría y debugging.
     * 
     * @param array $tool_calls Tool calls originales
     * @param array $outputs Outputs ejecutados (resultado de execute_registered_tools)
     * @param int $round Número de ronda (1-based)
     * @param array $context Contexto con request_uuid, session_id, bot_slug
     */
    protected function log_tool_executions( $tool_calls, $outputs, $round, $context = [] ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aichat_tool_calls';
        
        foreach ( $outputs as $output ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal tool execution audit log.
            $wpdb->insert( $table, [
                'request_uuid' => $context['request_uuid'] ?? '',
                'conversation_id' => null, // Se vincula después en process_message
                'session_id' => $context['session_id'] ?? '',
                'bot_slug' => $context['bot_slug'] ?? '',
                'round' => $round,
                'call_id' => $output['tool_call_id'],
                'tool_name' => $output['name'],
                'arguments_json' => $output['arguments'] ?? '{}',
                'output_excerpt' => $output['output'],
                'duration_ms' => $output['elapsed_ms'],
                'error_code' => (strpos($output['output'], '"error"') !== false ? 'error' : null),
                'created_at' => current_time('mysql'),
            ], [
                '%s', // request_uuid
                '%d', // conversation_id
                '%s', // session_id
                '%s', // bot_slug
                '%d', // round
                '%s', // call_id
                '%s', // tool_name
                '%s', // arguments_json
                '%s', // output_excerpt
                '%d', // duration_ms
                '%s', // error_code
                '%s', // created_at
            ]);
        }
    }
    
    /**
     * Obtener límite de rondas configurado
     * 
     * Consulta el filtro 'aichat_tools_max_rounds' para permitir
     * configuración dinámica del límite de rondas.
     * 
     * @param array $params Parámetros con bot, session
     * @return int Máximo de rondas (mínimo 1)
     */
    protected function get_max_rounds( $params = [] ) {
        $max = (int) apply_filters( 
            'aichat_tools_max_rounds', 
            5, 
            $params['bot'] ?? null, 
            $params['session'] ?? null 
        );
        
        return $max < 1 ? 1 : $max;
    }
}
