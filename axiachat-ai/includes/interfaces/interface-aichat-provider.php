<?php
/**
 * Interfaz para proveedores AI
 * 
 * Define el contrato que deben cumplir todos los adapters de proveedores.
 * Cada proveedor (OpenAI, Claude, Ollama, etc.) implementa esta interfaz.
 * 
 * @package AIChat
 * @since 2.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

interface AIChat_Provider_Interface {
    
    /**
     * Constructor con configuración del proveedor
     * 
     * @param array $config Configuración específica del proveedor
     *                      Ejemplos: ['api_key' => '...', 'endpoint' => '...']
     */
    public function __construct( $config = [] );
    
    /**
     * Obtener ID único del proveedor
     * 
     * @return string ID del proveedor (ej: 'openai', 'claude', 'ollama')
     */
    public function get_id();
    
    /**
     * Llamada principal al modelo (chat completion)
     * 
     * Este método debe normalizar la respuesta a un formato estándar
     * independientemente del proveedor subyacente.
     * 
     * @param array $messages Array de mensajes en formato OpenAI:
     *                        [
     *                          ['role' => 'system', 'content' => '...'],
     *                          ['role' => 'user', 'content' => '...'],
     *                          ['role' => 'assistant', 'content' => '...']
     *                        ]
     * 
     * @param array $params Parámetros de la llamada:
     *                      [
     *                        'model' => string,       // ID del modelo a usar
     *                        'temperature' => float,  // 0.0 - 2.0
     *                        'max_tokens' => int,     // Máximo de tokens en respuesta
     *                        'tools' => array         // (Opcional) Definición de tools
     *                      ]
     * 
     * @return array Formato estándar de respuesta:
     *               [
     *                 'message' => string,              // Texto de la respuesta del modelo
     *                 'usage' => [                      // Uso de tokens
     *                   'prompt_tokens' => int,         // Tokens de entrada
     *                   'completion_tokens' => int,     // Tokens de salida
     *                   'total_tokens' => int           // Total
     *                 ],
     *                 'finish_reason' => string,        // (Opcional) 'stop', 'length', 'tool_calls'
     *                 'tool_calls' => array             // (Opcional) Llamadas a tools si aplica
     *               ]
     * 
     *               En caso de error:
     *               [
     *                 'error' => string                 // Mensaje de error
     *               ]
     */
    public function chat( $messages, $params = [] );
    
    /**
     * Calcular coste de la llamada en microcents
     * 
     * @param array $usage Array con información de tokens:
     *                     ['prompt_tokens' => int, 'completion_tokens' => int, 'total_tokens' => int]
     * 
     * @param string $model ID del modelo usado
     * 
     * @return int|null Coste en microcents (1 USD cent = 10,000 microcents)
     *                  Retorna null si el coste no es calculable o no aplica (ej: modelos locales)
     */
    public function calculate_cost( $usage, $model );
}
