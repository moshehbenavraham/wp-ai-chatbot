<?php
/**
 * Google Gemini Provider Class
 * 
 * Adapter for Google Gemini API (generativelanguage.googleapis.com)
 * Implements the AIChat_Provider interface for multi-provider architecture.
 * 
 * Key differences from OpenAI/Claude:
 * - Messages format: contents[] with parts[] instead of content string
 * - System prompt: systemInstruction separate field (not a message)
 * - Config: generationConfig object (not root-level params)
 * - Thinking mode: REQUIRED for pro models (2.5-pro, 3-pro), optional for flash/flash-lite (disabled by default)
 * - Streaming: Separate endpoint :streamGenerateContent
 * - Multi-tool support: Can combine native tools (googleSearch, codeExecution) with functionDeclarations
 * - Web search: Native googleSearch tool (injected via aichat_gemini_tools filter when web_search macro enabled)
 * 
 * @package AIChat
 * @subpackage Providers
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load trait for tool execution
require_once dirname(__FILE__) . '/../traits/trait-aichat-tool-execution.php';

class AIChat_Gemini_Provider implements AIChat_Provider_Interface {
    
    // Use trait for tool execution
    use AIChat_Tool_Execution;
    
    /**
     * Gemini API key
     * @var string
     */
    private $api_key;
    
    /**
     * Gemini API base URL
     * @var string
     */
    private $api_base = 'https://generativelanguage.googleapis.com/v1beta';
    
    /**
     * Default model
     * @var string
     */
    private $default_model = 'gemini-2.5-flash';
    
    /**
     * Configuration array
     * @var array
     */
    protected $config = [];
    
    /**
     * Pricing table (per 1M tokens, paid tier) — derived from model registry.
     * @var array
     */
    private $pricing = [];
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array ['api_key' => string]
     */
    public function __construct($config = []) {
        $this->config = $config;
        
        // Populate default model and pricing from centralised registry
        if ( function_exists( 'aichat_get_default_model' ) ) {
            $this->default_model = aichat_get_default_model( 'gemini' );
        }
        if ( function_exists( 'aichat_get_models_for_provider' ) ) {
            foreach ( aichat_get_models_for_provider( 'gemini' ) as $id => $m ) {
                $this->pricing[ $id ] = [ 'input' => $m['pricing']['input'], 'output' => $m['pricing']['output'] ];
            }
        }
        
        // Debug: Check what we receive in config
        if (isset($config['api_key'])) {
            aichat_log_debug('[Gemini Provider] Constructor received api_key in config (length: ' . strlen($config['api_key']) . ')');
        } else {
            aichat_log_debug('[Gemini Provider] Constructor: no api_key in config, will try aichat_get_setting');
        }
        
        // Get API key from config or fallback to option (use aichat_get_setting for decryption)
        $this->api_key = $config['api_key'] ?? aichat_get_setting('aichat_gemini_api_key');
        
        // Trim whitespace that might cause issues
        $this->api_key = trim($this->api_key);
        
        // Debug: Final API key length after trim
        aichat_log_debug('[Gemini Provider] Constructor: final api_key length: ' . strlen($this->api_key) . ' chars');
        
        // Debug: Check for non-printable characters
        $clean_key = preg_replace('/[^\x20-\x7E]/', '', $this->api_key);
        if (strlen($clean_key) !== strlen($this->api_key)) {
            aichat_log_debug('[Gemini Provider] WARNING: API key contains non-printable characters!');
            aichat_log_debug('[Gemini Provider] Original length: ' . strlen($this->api_key) . ', Clean length: ' . strlen($clean_key));
        }
        
        // Allow override of default model
        $custom_default = get_option('aichat_gemini_default_model', '');
        if (!empty($custom_default)) {
            $this->default_model = $custom_default;
        }
    }
    
    /**
     * Obtener ID del proveedor
     * 
     * @return string
     */
    public function get_id() {
        return 'gemini';
    }
    
    /**
     * Main chat method - unified entry point
     * 
     * Routes to chat_with_tools() to allow filter injection of server-side tools
     * like web_search (googleSearch) even when the tools array is initially empty.
     * - chat_with_tools() applies aichat_gemini_tools filter
     * - If tools array is still empty after filter, it falls back to simple generation
     * 
     * @param array $messages Array of message objects with role and content
     * @param array $params Additional parameters (model, temperature, max_tokens, tools, etc.)
     * @return array Response with 'message' or 'error'
     */
    public function chat($messages, $params = []) {
        aichat_log_debug('[Gemini Provider] chat() called with ' . count($messages) . ' messages');
        
        // Validate API key
        if (empty($this->api_key)) {
            $agency_enabled = ( function_exists('aichat_agency_is_configured') && aichat_agency_is_configured() )
                || (bool) get_option( 'aichat_agency_enabled', false );
            if ( ! $agency_enabled ) {
                aichat_log_debug('[Gemini Provider] ERROR: API key not configured');
                return [
                    'error' => 'Gemini API key not configured. Please add your API key in plugin settings.'
                ];
            }
            $this->api_key = 'proxy';
        }
        
        // Set default model if not provided
        if (empty($params['model'])) {
            $params['model'] = $this->default_model;
            aichat_log_debug('[Gemini Provider] Using default model: ' . $this->default_model);
        }
        
        // ALWAYS route to chat_with_tools even if tools array is empty
        // This allows filters (like aichat_gemini_tools) to inject server-side tools like googleSearch
        // The method will apply filters and then decide whether to use tools or fallback to simple chat
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            $tools_count = isset($params['tools']) && is_array($params['tools']) ? count($params['tools']) : 0;
            aichat_log_debug('[Gemini Provider] Routing to chat_with_tools (tools count before filter: ' . $tools_count . ')');
        }
        
        return $this->chat_with_tools($messages, $params);
    }
    
    /**
     * Simple text chat (no tools)
     * 
     * Handles basic text generation with Gemini models.
     * Supports all generation parameters and thinking mode configuration.
     * 
     * @param array $messages Message history
     * @param array $params Generation parameters
     * @return array Response with message and metadata
     */
    protected function chat_simple($messages, $params) {
        aichat_log_debug('[Gemini Provider] chat_simple() - Converting messages to Gemini format');
        
        // 1. Convert our message format to Gemini API format
        $gemini_request = $this->convert_messages_to_gemini($messages, $params);
        
        if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
            aichat_log_debug('[Gemini Provider] Request body: ' . json_encode($gemini_request, JSON_PRETTY_PRINT));
        }
        
        // 2. Make API request
        $model = $params['model'] ?? $this->default_model;
        $endpoint = "{$this->api_base}/models/{$model}:generateContent";
        
        aichat_log_debug('[Gemini Provider] Calling endpoint: ' . $endpoint);
        
        $response = $this->make_request($endpoint, $gemini_request);
        
        // 3. Process and return response
        return $this->process_response($response, $params);
    }
    
    /**
     * Convert our message format to Gemini API format
     * 
     * Gemini differences:
     * - System message goes to systemInstruction (not in contents)
     * - Messages use contents[] with role and parts[]
     * - Assistant role is called "model" in Gemini
     * - Parameters go in generationConfig object
     * - Thinking mode disabled by default (set thinkingBudget: 0)
     * 
     * @param array $messages Our message format
     * @param array $params Generation parameters
     * @return array Gemini API request body
     */
    protected function convert_messages_to_gemini($messages, $params) {
        $system_instruction = null;
        $contents = [];
        
        // Separate system message from conversation
        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                // System instruction is separate in Gemini
                $system_instruction = [
                    'parts' => [['text' => $msg['content']]]
                ];
                // aichat_log_debug('[Gemini Provider] Extracted system instruction');
            } 
            // Check if message is already in Gemini format (has 'parts' instead of 'content')
            elseif (isset($msg['parts'])) {
                // Already in Gemini format — sanitize functionCall.args and pass through
                $contents[] = [
                    'role' => $msg['role'], // Already 'model' or 'user'
                    'parts' => $this->sanitize_parts_for_api($msg['parts'])
                ];
            }
            else {
                // Convert from universal format to Gemini format
                // Convert assistant -> model (Gemini terminology)
                $role = $msg['role'] === 'assistant' ? 'model' : 'user';
                $contents[] = [
                    'role' => $role,
                    'parts' => [['text' => $msg['content']]]
                ];
            }
        }
        
        // aichat_log_debug('[Gemini Provider] Converted to ' . count($contents) . ' content items');
        
        // Build request body
        $request = [
            'contents' => $contents
        ];
        
        // Add system instruction if present
        if ($system_instruction) {
            $request['systemInstruction'] = $system_instruction;
        }
        
        // Build generation config
        $generation_config = [];
        
        // Temperature (0.0 - 2.0)
        if (isset($params['temperature'])) {
            $generation_config['temperature'] = (float) $params['temperature'];
        }
        
        // Max output tokens
        if (isset($params['max_tokens'])) {
            $generation_config['maxOutputTokens'] = (int) $params['max_tokens'];
        }
        
        // Top P (nucleus sampling)
        if (isset($params['top_p'])) {
            $generation_config['topP'] = (float) $params['top_p'];
        }
        
        // Top K (only if specified - Gemini uses both top-k and nucleus)
        if (isset($params['top_k'])) {
            $generation_config['topK'] = (int) $params['top_k'];
        }
        
        // Stop sequences (max 5)
        if (isset($params['stop']) && is_array($params['stop'])) {
            $generation_config['stopSequences'] = array_slice($params['stop'], 0, 5);
        }
        
        // Presence penalty
        if (isset($params['presence_penalty'])) {
            $generation_config['presencePenalty'] = (float) $params['presence_penalty'];
        }
        
        // Frequency penalty
        if (isset($params['frequency_penalty'])) {
            $generation_config['frequencyPenalty'] = (float) $params['frequency_penalty'];
        }
        
        // Seed for reproducibility
        if (isset($params['seed'])) {
            $generation_config['seed'] = (int) $params['seed'];
        }
        
        // Response MIME type (for JSON mode)
        if (isset($params['response_format']) && $params['response_format'] === 'json_object') {
            $generation_config['responseMimeType'] = 'application/json';
        }
        
        // Thinking mode configuration (2.5+ models including Gemini 3)
        $model = $params['model'] ?? $this->default_model;
        $is_2_5_model = strpos($model, '2.5') !== false;
        $is_3_model   = strpos($model, 'gemini-3') !== false;
        $is_thinking_capable = $is_2_5_model || $is_3_model;
        $is_pro_model = strpos($model, 'gemini-2.5-pro') !== false
                     || strpos($model, 'gemini-3-pro') !== false;
        
        if ($is_thinking_capable) {
            // Pro models REQUIRE thinking mode (cannot disable)
            // Other thinking-capable models support optional thinking
            if ($is_pro_model) {
                // Always enable thinking for pro models (required)
                $generation_config['thinkingConfig'] = [
                    'includeThoughts' => isset($params['include_thoughts']) ? (bool)$params['include_thoughts'] : false,
                    'thinkingBudget' => isset($params['thinking_budget']) ? (int) $params['thinking_budget'] : 8192
                ];
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[Gemini Provider] Thinking mode REQUIRED for ' . $model, [
                        'budget' => $generation_config['thinkingConfig']['thinkingBudget'],
                        'include_thoughts' => $generation_config['thinkingConfig']['includeThoughts']
                    ], true);
                }
            } elseif (isset($params['enable_thinking']) && $params['enable_thinking']) {
                // Enable thinking mode for other thinking-capable models (optional)
                $generation_config['thinkingConfig'] = [
                    'includeThoughts' => isset($params['include_thoughts']) ? (bool)$params['include_thoughts'] : true,
                    'thinkingBudget' => isset($params['thinking_budget']) ? (int) $params['thinking_budget'] : 8192
                ];
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[Gemini Provider] Thinking mode ENABLED', [
                        'budget' => $generation_config['thinkingConfig']['thinkingBudget']
                    ], true);
                }
            } else {
                // Disable thinking for non-pro thinking-capable models (flash, flash-lite)
                $generation_config['thinkingConfig'] = [
                    'thinkingBudget' => 0
                ];
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[Gemini Provider] Thinking mode DISABLED (thinkingBudget: 0)', [], true);
                }
            }
        }
        
        $request['generationConfig'] = $generation_config;
        
        // Safety settings (optional - can be configured via params)
        if (isset($params['safety_settings']) && is_array($params['safety_settings'])) {
            $request['safetySettings'] = $params['safety_settings'];
        }
        
        return $request;
    }
    
    /**
     * Make HTTP request to Gemini API
     * 
     * Uses WordPress HTTP API with API key in query parameter.
     * Gemini accepts key via ?key=YOUR_API_KEY or x-goog-api-key header.
     * Using query param for simplicity.
     * 
     * @param string $endpoint Full API endpoint URL
     * @param array $body Request body
     * @return array Decoded JSON response or error array
     */
    protected function make_request($endpoint, $body) {
        // Debug: Check if API key is set
        if (empty($this->api_key)) {
            $agency_enabled = ( function_exists('aichat_agency_is_configured') && aichat_agency_is_configured() )
                || (bool) get_option( 'aichat_agency_enabled', false );
            if ( ! $agency_enabled ) {
                aichat_log_debug('[Gemini Provider] ERROR: API key is empty!');
                return [
                    'error' => 'Gemini API key not configured or empty'
                ];
            }
            $this->api_key = 'proxy';
        }
        
        aichat_log_debug('[Gemini Provider] API key length: ' . strlen($this->api_key) . ' chars');
        
        // Add API key as query parameter
        $url = add_query_arg('key', $this->api_key, $endpoint);
        
        // Mask key in logs
        $safe_url = preg_replace('/key=[^&]+/', 'key=***', $url);
        aichat_log_debug('[Gemini Provider] POST to: ' . $safe_url);
        
        $response = wp_remote_post($url, [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($body),
            'timeout' => 60,
            'sslverify' => true
        ]);
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            aichat_log_debug('[Gemini Provider] HTTP Error: ' . $error_message);
            return [
                'error' => 'HTTP request failed: ' . $error_message
            ];
        }
        
        // Get response body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        aichat_log_debug('[Gemini Provider] Response code: ' . $response_code);
        
        // Decode JSON
        $decoded = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            aichat_log_debug('[Gemini Provider] JSON decode error: ' . json_last_error_msg());
            return [
                'error' => 'Invalid JSON response from Gemini API',
                'raw_response' => substr($response_body, 0, 500)
            ];
        }
        
        // Check for API errors
        if ($response_code !== 200) {
            $error_msg = $decoded['error']['message'] ?? 'Unknown API error';
            aichat_log_debug('[Gemini Provider] API Error: ' . $error_msg);
            
            // Add helpful context for common errors
            if ($response_code === 400) {
                $error_msg .= ' (Bad Request - check request format)';
            } elseif ($response_code === 401) {
                $error_msg .= ' (Unauthorized - check API key)';
            } elseif ($response_code === 429) {
                $error_msg .= ' (Rate limit exceeded - try again later)';
            } elseif ($response_code >= 500) {
                $error_msg .= ' (Server error - Gemini API issue)';
            }
            
            return [
                'error' => $error_msg,
                'error_code' => $response_code
            ];
        }
        
        return $decoded;
    }
    
    /**
     * Process Gemini API response
     * 
     * Extracts message text, usage metadata, and finish reason.
     * Handles safety blocks and other edge cases.
     * 
     * @param array $response Raw API response
     * @param array $params Original request params
     * @return array Normalized response
     */
    protected function process_response($response, $params) {
        // Check for errors
        if (isset($response['error'])) {
            return [
                'error' => $response['error'],
                'error_code' => $response['error_code'] ?? null
            ];
        }
        
        // Check if we have candidates
        if (empty($response['candidates']) || !is_array($response['candidates'])) {
            aichat_log_debug('[Gemini Provider] No candidates in response');
            
            // Check for prompt feedback (blocked prompt)
            if (isset($response['promptFeedback']['blockReason'])) {
                $block_reason = $response['promptFeedback']['blockReason'];
                $safety_ratings = $response['promptFeedback']['safetyRatings'] ?? [];
                
                aichat_log_debug('[Gemini Provider] Prompt blocked: ' . $block_reason);
                
                return [
                    'error' => 'Prompt blocked by safety filters: ' . $block_reason,
                    'safety_ratings' => $safety_ratings
                ];
            }
            
            return [
                'error' => 'No response generated (empty candidates)',
                'raw_response' => $response
            ];
        }
        
        $candidate = $response['candidates'][0];
        
        // Extract text from parts
        $text = '';
        $has_function_calls = false;
        
        if (isset($candidate['content']['parts']) && is_array($candidate['content']['parts'])) {
            foreach ($candidate['content']['parts'] as $part) {
                if (isset($part['text'])) {
                    $text .= $part['text'];
                }
                if (isset($part['functionCall'])) {
                    $has_function_calls = true;
                }
            }
        }
        
        // If we have function calls, that's valid (even without text)
        if ($has_function_calls) {
            aichat_log_debug('[Gemini Provider] Response contains function calls (no text expected)');
            // Return empty message - function calls will be processed separately
            return [
                'message' => '',
                'usage' => [
                    'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                    'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                    'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
                ],
                'finish_reason' => $candidate['finishReason'] ?? 'STOP',
                'has_function_calls' => true
            ];
        }
        
        if (empty($text)) {
            aichat_log_debug('[Gemini Provider] No text in candidate parts and no function calls');
            
            // Check finish reason
            $finish_reason = $candidate['finishReason'] ?? 'UNKNOWN';
            
            if ($finish_reason === 'SAFETY') {
                return [
                    'error' => 'Response blocked by safety filters',
                    'safety_ratings' => $candidate['safetyRatings'] ?? []
                ];
            }
            
            // STOP with no text: can happen after tool execution when model
            // considers the task done.  Return empty message instead of error
            // so the caller can provide a graceful fallback.
            if ($finish_reason === 'STOP') {
                aichat_log_debug('[Gemini Provider] Empty text with STOP — returning empty message (post-tool pattern)');
                return [
                    'message' => '',
                    'usage' => [
                        'prompt_tokens'     => $response['usageMetadata']['promptTokenCount'] ?? 0,
                        'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
                        'total_tokens'      => $response['usageMetadata']['totalTokenCount'] ?? 0,
                    ],
                    'finish_reason' => 'STOP',
                ];
            }
            
            return [
                'error' => 'No text content generated (finish reason: ' . $finish_reason . ')',
                'finish_reason' => $finish_reason
            ];
        }
        
        // Extract usage metadata
        $usage = [
            'prompt_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
            'completion_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
            'total_tokens' => $response['usageMetadata']['totalTokenCount'] ?? 0,
        ];
        
        // Add thinking tokens for 2.5 models
        if (isset($response['usageMetadata']['thoughtsTokenCount'])) {
            $usage['thoughts_tokens'] = $response['usageMetadata']['thoughtsTokenCount'];
            aichat_log_debug('[Gemini Provider] Thinking tokens used: ' . $usage['thoughts_tokens']);
        }
        
        // Get finish reason
        $finish_reason = $candidate['finishReason'] ?? 'UNKNOWN';
        
        aichat_log_debug('[Gemini Provider] Response: ' . strlen($text) . ' chars, ' . $usage['total_tokens'] . ' tokens, finish: ' . $finish_reason);
        
        // Check for grounding metadata (Google Search results)
        $grounding_metadata = null;
        $web_search_sources = [];
        
        if (isset($candidate['groundingMetadata'])) {
            $grounding_metadata = $candidate['groundingMetadata'];
            
            // Extract web search sources from grounding chunks
            if (!empty($grounding_metadata['groundingChunks'])) {
                foreach ($grounding_metadata['groundingChunks'] as $idx => $chunk) {
                    if (isset($chunk['web'])) {
                        $web_search_sources[] = [
                            'index' => $idx,
                            'url' => $chunk['web']['uri'] ?? '',
                            'title' => $chunk['web']['title'] ?? 'Untitled'
                        ];
                    }
                }
                
                if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                    aichat_log_debug('[Gemini Provider] Google Search sources found', [
                        'source_count' => count($web_search_sources),
                        'web_search_queries' => $grounding_metadata['webSearchQueries'] ?? [],
                        'sources' => array_map(function($s) {
                            return $s['title'] . ' - ' . $s['url'];
                        }, $web_search_sources)
                    ], true);
                }
            }
        }
        
        // Check for citation metadata (legacy/alternate format)
        $citations = null;
        if (isset($candidate['citationMetadata']['citationSources'])) {
            $citations = $candidate['citationMetadata']['citationSources'];
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[Gemini Provider] Citation metadata present: ' . count($citations) . ' sources');
            }
        }
        
        return [
            'message' => $text,
            'usage' => $usage,
            'finish_reason' => $finish_reason,
            'model' => $params['model'] ?? $this->default_model,
            'grounding_metadata' => $grounding_metadata,
            'web_search_sources' => $web_search_sources,
            'citations' => $citations,
            'safety_ratings' => $candidate['safetyRatings'] ?? [],
            'raw_response' => $response // For debugging
        ];
    }
    
    /**
     * Chat with tools (function calling)
     * 
     * Implements multi-round tool execution loop:
     * 1. Send messages with available tools
     * 2. If model returns function_call parts, execute them
     * 3. Send results back and continue until final response
     * 
     * Supports both client-side function execution and native tools like googleSearch.
     * 
     * @param array $messages Initial conversation messages
     * @param array $params Request parameters including tools array
     * @return array Response with message and metadata
     */
    protected function chat_with_tools($messages, $params) {
    $model = $params['model'] ?? $this->default_model;
    $tools = $params['tools'] ?? [];
    $max_rounds = $params['max_tool_rounds'] ?? 5;
    $conversation = $messages;

    $request_uuid = $params['request_uuid'] ?? wp_generate_uuid4();
    $params['request_uuid'] = $request_uuid;
    $session_id = $params['session_id'] ?? '';
    $bot_slug = $params['bot_slug'] ?? '';
    $usage_accumulator = null;
        
        aichat_log_debug('[Gemini Provider] Starting tool execution loop (max rounds: ' . $max_rounds . ')');
        
        // Apply provider-specific tools filter BEFORE converting to Gemini format
        // This allows filters to inject server-side tools like google_search
        $ctx = [
            'model' => $model,
            'bot' => $params['bot_slug'] ?? null
        ];
        $tools = apply_filters( 'aichat_gemini_tools', $tools, $ctx );
        
        if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
            aichat_log_debug('[Gemini Provider] After applying aichat_gemini_tools filter', [
                'tools_count' => count($tools),
                'tools' => array_map(function($t) {
                    return [
                        'type' => $t['type'] ?? 'function',
                        'name' => $t['function']['name'] ?? $t['name'] ?? '?'
                    ];
                }, $tools)
            ], true);
        }
        
        // Convert tools to Gemini format
        $gemini_tools = $this->build_gemini_tools($tools);
        
        // If no tools after filter, fall back to simple chat for efficiency
        if (empty($gemini_tools)) {
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[Gemini Provider] No tools after filter, falling back to chat_simple()');
            }
            return $this->chat_simple($messages, $params);
        }
        
        if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
            aichat_log_debug('[Gemini Provider] Gemini tools structure: ' . json_encode($gemini_tools, JSON_PRETTY_PRINT));
        }
        
        $hallucination_retried = false;
        $empty_text_retried    = false;

        for ($round = 1; $round <= $max_rounds; $round++) {
            // aichat_log_debug('[Gemini Provider] Tool round ' . $round);
            
            // Prepare request with current conversation + tools
            $gemini_request = $this->convert_messages_to_gemini($conversation, $params);
            
            // Add tools to request
            if (!empty($gemini_tools)) {
                $gemini_request['tools'] = $gemini_tools;
            }
            
            // Build endpoint
            $endpoint = $this->api_base . '/models/' . $model . ':generateContent';
            
            // Make API call
            $response = $this->make_request($endpoint, $gemini_request);
            
            if (isset($response['error'])) {
                return $response;
            }
            
            // Process response
            $result = $this->process_response($response, $params);
            
            if (isset($result['error'])) {
                return $result;
            }

            if (isset($result['usage']) && is_array($result['usage'])) {
                $usage_accumulator = $this->accumulate_usage($usage_accumulator, $result['usage']);
            }
            
            // Check for function calls in response
            $function_calls = $this->extract_function_calls($response);
            
            if (empty($function_calls)) {
                $text = $result['message'] ?? '';

                // Detect hallucinated tool execution: model outputs narration text like
                // "(executing tools…)" instead of actually emitting a functionCall.
                // Retry once with a nudge asking the model to call the tool directly.
                if ( ! $hallucination_retried && ! empty( $gemini_tools ) && $this->is_hallucinated_tool_text( $text ) ) {
                    $hallucination_retried = true;
                    // Append model turn + user nudge so the model retries
                    $conversation[] = [ 'role' => 'model', 'parts' => [ [ 'text' => $text ] ] ];
                    $conversation[] = [ 'role' => 'user',  'parts' => [ [ 'text' => 'Do not describe tool usage in text. You must call the tool function directly using a functionCall. Please proceed.' ] ] ];
                    $max_rounds++; // compensate for the wasted round
                    aichat_log_debug( '[Gemini Provider] Hallucinated tool execution detected ("' . mb_substr( $text, 0, 80 ) . '"). Retrying with nudge (round ' . $round . ')' );
                    continue;
                }

                // Empty text after tool execution: model returned STOP with no content.
                // Retry once asking it to produce a user-visible answer.
                if ( $text === '' && ! $empty_text_retried && $round > 1 ) {
                    $empty_text_retried = true;
                    $conversation[] = [ 'role' => 'user', 'parts' => [ [ 'text' => 'Please provide a brief confirmation message to the user summarising what was done.' ] ] ];
                    $max_rounds++;
                    aichat_log_debug( '[Gemini Provider] Empty text after tool round — retrying with nudge (round ' . $round . ')' );
                    continue;
                }

                // No function calls - this is the final response
                // aichat_log_debug('[Gemini Provider] No function calls, returning final response');
                if ($usage_accumulator !== null) {
                    $result['usage'] = $usage_accumulator;
                }
                $result['model'] = $model;
                return $result;
            }
            
            if ($round === 1) {
                $pending_tool_calls = [];
                foreach ($function_calls as $fc) {
                    $tool_name = $fc['name'] ?? '';
                    $raw_args = $fc['args'] ?? [];
                    if ( $raw_args instanceof \stdClass ) {
                        $raw_args = (array) $raw_args;
                    }
                    $pending_tool_calls[] = [
                        'id' => $fc['id'] ?? ('call_' . wp_generate_uuid4()),
                        'name' => $tool_name,
                        'arguments' => wp_json_encode($raw_args),
                        'activity_label' => $this->build_activity_label($tool_name),
                    ];
                }

                // Preserve model turn (contains functionCall + optional thought signatures)
                $model_content = $this->build_model_content_with_function_calls($response);
                $conversation_with_model = $conversation;
                $conversation_with_model[] = $model_content;

                $response_id = wp_generate_uuid4();

                global $wpdb;
                $table = $wpdb->prefix . 'aichat_tool_states';

                $state_payload = [
                    'conversation' => $conversation_with_model,
                    'params' => $params,
                    'gemini_tools' => $gemini_tools,
                    'model' => $model,
                    'max_rounds' => $max_rounds,
                    'round' => $round,
                    'usage' => $usage_accumulator,
                    'request_context' => [
                        'request_uuid' => $request_uuid,
                        'session_id' => $session_id,
                        'bot_slug' => $bot_slug,
                    ],
                    'function_calls' => $function_calls,
                ];

                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal tool state persistence.
                $inserted = $wpdb->insert(
                    $table,
                    [
                        'response_id' => $response_id,
                        'state_data' => maybe_serialize($state_payload),
                        'created_at' => current_time('mysql'),
                    ],
                    ['%s', '%s', '%s']
                );

                if (! $inserted) {
                    aichat_log_debug('[Gemini Provider] Failed to persist tool state', [
                        'response_id' => $response_id,
                        'error' => $wpdb->last_error,
                    ], true);

                    return [
                        'error' => __('Failed to store Gemini tool state.', 'axiachat-ai')
                    ];
                }

                if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
                    aichat_log_debug('[Gemini Provider] Returning tool_pending handshake', [
                        'response_id' => $response_id,
                        'tool_count' => count($pending_tool_calls),
                    ], true);
                }

                return [
                    'status' => 'tool_pending',
                    'response_id' => $response_id,
                    'tool_calls' => $pending_tool_calls,
                    'request_uuid' => $request_uuid,
                    'usage' => $usage_accumulator,
                    'model' => $model,
                ];
            }

            // Execute function calls
            aichat_log_debug('[Gemini Provider] Executing ' . count($function_calls) . ' function call(s)');
            
            // Convert to format expected by trait (OpenAI-style tool_calls)
            $tool_calls_for_trait = $this->convert_gemini_function_calls_to_tool_calls($function_calls);
            // aichat_log_debug('[Gemini Provider] Tool calls for trait: ' . json_encode($tool_calls_for_trait, JSON_PRETTY_PRINT));
            
            // Execute using trait
            $tool_outputs = $this->execute_registered_tools($tool_calls_for_trait, [
                'request_uuid' => $request_uuid,
                'session_id' => $session_id,
                'bot_slug' => $bot_slug,
                'round' => $round
            ]);
            
            // Log executions
            $this->log_tool_executions($tool_calls_for_trait, $tool_outputs, $round, [
                'request_uuid' => $request_uuid,
                'session_id' => $session_id,
                'bot_slug' => $bot_slug,
            ]);
            
            // NOTE: Unlike OpenAI/Claude, Gemini doesn't want us to echo back the model's functionCall.
            // We only send the functionResponse from the user role.
            
            // Build function response content from tool outputs
            $function_response_content = $this->build_function_response_from_outputs($tool_outputs);
            
            // if (defined('AICHAT_DEBUG') && AICHAT_DEBUG) {
            //     aichat_log_debug('[Gemini Provider] Adding function response: ' . json_encode($function_response_content, JSON_PRETTY_PRINT));
            // }
            
            $conversation[] = $function_response_content;
            
            // Continue to next round with updated conversation
        }
        
        // Max rounds reached
        aichat_log_debug('[Gemini Provider] Max tool rounds reached without final answer');
        return [
            'error' => 'Maximum tool execution rounds reached without final answer'
        ];
    }

    /**
     * Continue Gemini conversation after tool_pending handshake
     *
     * Executes requested tools and resumes the loop until the model returns a
     * final answer or the maximum number of rounds is reached.
     *
     * @param string $response_id Stored tool state identifier
     * @param array  $tool_calls  Tool call payload from frontend
     * @return array Final response or error structure
     */
    public function continue_from_tool_pending($response_id, $tool_calls) {
        global $wpdb;

        aichat_log_debug('[Gemini Provider] continue_from_tool_pending called', [
            'response_id' => $response_id,
            'tool_count' => is_array($tool_calls) ? count($tool_calls) : 0,
        ]);

        if (empty($response_id)) {
            return [
                'error' => __('Missing response_id for Gemini continuation.', 'axiachat-ai')
            ];
        }

        $table = $wpdb->prefix . 'aichat_tool_states';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal tool state lookup.
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT state_data FROM {$table} WHERE response_id = %s", $response_id )
        );

        if (!$row || empty($row->state_data)) {
            aichat_log_debug('[Gemini Provider] Tool state not found', [
                'response_id' => $response_id,
            ]);

            return [
                'error' => __('Gemini tool state not found or expired.', 'axiachat-ai')
            ];
        }

        // One-time use state – delete immediately to avoid reuse
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal tool state cleanup.
        $wpdb->delete($table, ['response_id' => $response_id], ['%s']);

        $state = maybe_unserialize($row->state_data);
        if (!is_array($state)) {
            return [
                'error' => __('Invalid Gemini tool state payload.', 'axiachat-ai')
            ];
        }

        $conversation = $state['conversation'] ?? [];
        $params = $state['params'] ?? [];
        $gemini_tools = $state['gemini_tools'] ?? [];
        $model = $state['model'] ?? ($params['model'] ?? $this->default_model);
        $max_rounds = $state['max_rounds'] ?? 5;
        $starting_round = $state['round'] ?? 1;
        $usage_accumulator = $state['usage'] ?? null;
        $request_context = $state['request_context'] ?? [];

        $request_uuid = $request_context['request_uuid'] ?? wp_generate_uuid4();
        $session_id = $request_context['session_id'] ?? '';
        $bot_slug = $request_context['bot_slug'] ?? '';

        $params['request_uuid'] = $request_uuid;
        $params['session_id'] = $session_id;
        $params['bot_slug'] = $bot_slug;

        aichat_log_debug('[Gemini Provider] Continuing from tool_pending', [
            'response_id' => $response_id,
            'model' => $model,
            'tool_count' => is_array($tool_calls) ? count($tool_calls) : 0,
            'round' => $starting_round,
        ], true);

        // Build authoritative args map from state's function_calls (persisted at handshake time)
        $state_function_calls = $state['function_calls'] ?? [];
        $state_args_map = [];
        foreach ( $state_function_calls as $idx => $sfc ) {
            $sfc_name = $sfc['name'] ?? '';
            $sfc_args = $sfc['args'] ?? [];
            // Args may be stdClass (from extract_function_calls cast) or array
            if ( $sfc_args instanceof \stdClass ) {
                $sfc_args = (array) $sfc_args;
            }
            // Key by index+name to support multiple calls to the same tool
            $state_args_map[ $idx ] = is_array( $sfc_args ) ? wp_json_encode( $sfc_args ) : (string) $sfc_args;
        }

        // Normalize tool calls — prefer state args over frontend args (frontend round-trip loses them)
        $normalized_tool_calls = [];
        if (is_array($tool_calls)) {
            foreach ($tool_calls as $idx => $tc) {
                // Frontend normalizer may use 'args' or 'arguments' depending on path
                $fe_args = $tc['arguments'] ?? ( $tc['args'] ?? '{}' );
                if ( is_array( $fe_args ) ) {
                    $fe_args = wp_json_encode( $fe_args );
                }

                // Use state args as authoritative source; fall back to frontend args
                $final_args = $state_args_map[ $idx ] ?? $fe_args;
                if ( $final_args === '[]' || $final_args === '' ) {
                    $final_args = '{}';
                }

                $normalized_tool_calls[] = [
                    'id'        => $tc['id'] ?? ( $tc['call_id'] ?? ( 'call_' . wp_generate_uuid4() ) ),
                    'name'      => $tc['name'] ?? '',
                    'arguments' => $final_args,
                ];
            }
        }

        if (empty($normalized_tool_calls)) {
            return [
                'error' => __('No tool calls supplied for Gemini continuation.', 'axiachat-ai')
            ];
        }

        $context = [
            'request_uuid' => $request_uuid,
            'session_id' => $session_id,
            'bot_slug' => $bot_slug,
            'round' => $starting_round,
        ];

        // Remove the model's functionCall turn that was saved at the end of the
        // conversation.  Just like the non-pending path in chat_with_tools(), Gemini
        // only needs the functionResponse — echoing the functionCall back can trigger
        // proto validation errors (e.g. args encoded as [] instead of {} after
        // the PHP json_decode/serialize round-trip).
        $last_idx = count($conversation) - 1;
        if ($last_idx >= 0) {
            $last_msg = $conversation[$last_idx];
            $has_fc = false;
            foreach (($last_msg['parts'] ?? []) as $p) {
                if (isset($p['functionCall'])) { $has_fc = true; break; }
            }
            if ($has_fc) {
                array_pop($conversation);
                aichat_log_debug('[Gemini Provider] Removed model functionCall turn from conversation (non-echo pattern)');
            }
        }

        // Execute tools requested in the initial handshake round
        $tool_outputs = $this->execute_registered_tools($normalized_tool_calls, $context);
        $this->log_tool_executions($normalized_tool_calls, $tool_outputs, $starting_round, $context);

        $conversation[] = $this->build_function_response_from_outputs($tool_outputs);

        $empty_text_retried = false;

        // Resume loop for subsequent rounds (handshake already satisfied)
        for ($round = $starting_round + 1; $round <= $max_rounds; $round++) {
            $gemini_request = $this->convert_messages_to_gemini($conversation, $params);

            if (!empty($gemini_tools)) {
                $gemini_request['tools'] = $gemini_tools;
            }

            // Debug: log contents structure before API call (helps diagnose proto errors)
            if ( ! empty( $gemini_request['contents'] ) ) {
                $dbg_parts = [];
                foreach ( $gemini_request['contents'] as $ci => $cnt ) {
                    $role = $cnt['role'] ?? '?';
                    $pk   = [];
                    foreach ( ( $cnt['parts'] ?? [] ) as $pi => $pt ) {
                        $pk[] = implode( '+', array_keys( $pt ) );
                    }
                    $dbg_parts[] = "[{$ci}]={$role}(" . implode( ',', $pk ) . ')';
                }
                aichat_log_debug( '[Gemini Provider] Continuation request contents: ' . implode( ' | ', $dbg_parts ) );
            }

            $endpoint = $this->api_base . '/models/' . $model . ':generateContent';
            $response = $this->make_request($endpoint, $gemini_request);

            if (isset($response['error'])) {
                return $response;
            }

            $result = $this->process_response($response, $params);
            if (isset($result['error'])) {
                return $result;
            }

            if (isset($result['usage']) && is_array($result['usage'])) {
                $usage_accumulator = $this->accumulate_usage($usage_accumulator, $result['usage']);
            }

            $function_calls = $this->extract_function_calls($response);
            if (empty($function_calls)) {
                $text = $result['message'] ?? '';

                // Empty text after tool execution: model returned STOP with no content.
                // Retry once asking it to produce a user-visible answer.
                if ( $text === '' && ! $empty_text_retried ) {
                    $empty_text_retried = true;
                    $conversation[] = [ 'role' => 'user', 'parts' => [ [ 'text' => 'Please provide a brief confirmation message to the user summarising what was done.' ] ] ];
                    $max_rounds++;
                    aichat_log_debug( '[Gemini Provider] Empty text after tool round in continuation — retrying with nudge (round ' . $round . ')' );
                    continue;
                }

                if ($usage_accumulator !== null) {
                    $result['usage'] = $usage_accumulator;
                }
                $result['model'] = $model;
                return $result;
            }

            // Additional tool rounds inside continuation – execute automatically
            $tool_calls_for_trait = $this->convert_gemini_function_calls_to_tool_calls($function_calls);

            $context['round'] = $round;
            $tool_outputs = $this->execute_registered_tools($tool_calls_for_trait, $context);
            $this->log_tool_executions($tool_calls_for_trait, $tool_outputs, $round, $context);

            $conversation[] = $this->build_function_response_from_outputs($tool_outputs);
        }

        return [
            'error' => __('Maximum Gemini tool rounds reached without final answer.', 'axiachat-ai')
        ];
    }
    
    /**
     * Build Gemini tools array from our universal format
     * 
     * Gemini supports multi-tool use by combining multiple tool types in a SINGLE Tool object.
     * According to the official API reference, a Tool object can contain multiple fields:
     * {
     *   "functionDeclarations": [...],
     *   "google_search": {},       // snake_case with underscore (NOT googleSearch)
     *   "code_execution": {}       // snake_case with underscore (NOT codeExecution)
     * }
     * 
     * Official documentation:
     * - Multi-tool use: https://ai.google.dev/gemini-api/docs/function-calling#multi-tool-use
     * - Tool schema: https://ai.google.dev/api/caching#Tool
     * - Grounding: https://ai.google.dev/gemini-api/docs/google-search
     * 
     * IMPORTANT: Use snake_case property names (google_search, code_execution),
     * not camelCase (googleSearch, codeExecution). The API will reject camelCase.
     * 
     * @param array $tools Tools in universal format
     * @return array Gemini-formatted tools (single Tool object with combined properties)
     */
    protected function build_gemini_tools($tools) {
        $function_declarations = [];
        $has_google_search = false;
        $has_code_execution = false;
        
        foreach ($tools as $tool) {
            $type = $tool['type'] ?? 'function';
            
            if ($type === 'function' && isset($tool['function'])) {
                // Client-side function
                $fn = $tool['function'];
                
                // Clean parameters schema for Gemini compatibility
                $parameters = $fn['parameters'] ?? ['type' => 'object', 'properties' => []];
                $cleaned_params = $this->sanitize_schema_for_gemini($parameters);
                
                $function_declarations[] = [
                    'name' => $fn['name'],
                    'description' => $fn['description'] ?? '',
                    'parameters' => $cleaned_params
                ];
            }
            elseif ($type === 'google_search' || $type === 'googleSearch') {
                // Native Gemini tool - Google Search (grounding)
                $has_google_search = true;
            }
            elseif ($type === 'code_execution' || $type === 'codeExecution') {
                // Native Gemini tool - Code Execution
                $has_code_execution = true;
            }
        }
        
        // Build a SINGLE Tool object combining all tool types
        // This is the canonical format according to API docs
        $tool_object = [];
        
        // Add function declarations if any
        if (!empty($function_declarations)) {
            $tool_object['functionDeclarations'] = $function_declarations;
        }
        
        // Add Google Search if requested (combined in same Tool object)
        // CRITICAL: API requires snake_case 'google_search', not camelCase 'googleSearch'
        // See: https://ai.google.dev/gemini-api/docs/function-calling#multi-tool-use
        if ($has_google_search) {
            $tool_object['google_search'] = (object)[];
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[Gemini Provider] Google Search enabled in Tool object (google_search)', [], true);
            }
        }
        
        // Add Code Execution if requested (combined in same Tool object)
        // Note: snake_case for consistency with API
        if ($has_code_execution) {
            $tool_object['code_execution'] = (object)[];
            
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) {
                aichat_log_debug('[Gemini Provider] Code Execution enabled in Tool object', [], true);
            }
        }
        
        // Return array with single Tool object (API expects tools[] array)
        return empty($tool_object) ? [] : [$tool_object];
    }
    
    /**
     * Sanitize JSON Schema for Gemini compatibility
     * 
     * Gemini only supports a subset of OpenAPI schema:
     * - Removes: additionalProperties, $schema, $defs, default, examples, etc.
     * - Keeps: type, properties, required, description, enum, items
     * 
     * @param array $schema JSON Schema object
     * @return array Cleaned schema
     */
    protected function sanitize_schema_for_gemini($schema) {
        if (!is_array($schema)) {
            return $schema;
        }
        
        // List of allowed keys in Gemini schema
        $allowed_keys = [
            'type',
            'description',
            'enum',
            'properties',
            'required',
            'items',
            'format',
            'minimum',
            'maximum',
            'minItems',
            'maxItems',
            'minLength',
            'maxLength'
        ];
        
        $cleaned = [];
        
        foreach ($schema as $key => $value) {
            // Skip unsupported fields
            if (!in_array($key, $allowed_keys, true)) {
                continue;
            }
            
            // Recursively clean nested structures
            if ($key === 'properties' && is_array($value)) {
                $cleaned_properties = [];
                foreach ($value as $prop_name => $prop_schema) {
                    $cleaned_properties[$prop_name] = $this->sanitize_schema_for_gemini($prop_schema);
                }
                $cleaned[$key] = $cleaned_properties;
            }
            elseif ($key === 'items' && is_array($value)) {
                $cleaned[$key] = $this->sanitize_schema_for_gemini($value);
            }
            else {
                $cleaned[$key] = $value;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Extract function calls from Gemini response
     * 
     * @param array $response Raw API response
     * @return array Array of function calls with name and args
     */
    protected function extract_function_calls($response) {
        $function_calls = [];
        
        if (!isset($response['candidates'][0]['content']['parts'])) {
            aichat_log_debug('[Gemini Provider] No parts in response for function call extraction');
            return $function_calls;
        }
        
        $parts = $response['candidates'][0]['content']['parts'];
        
        // aichat_log_debug('[Gemini Provider] Checking ' . count($parts) . ' parts for function calls');
        
        foreach ($parts as $idx => $part) {
            if (isset($part['functionCall'])) {
                $fc = $part['functionCall'];
                
                aichat_log_debug('[Gemini Provider] Found functionCall in part ' . $idx . ': ' . ($fc['name'] ?? 'unknown'));
                // aichat_log_debug('[Gemini Provider] functionCall details: ' . json_encode($fc, JSON_PRETTY_PRINT));
                
                // Use (object) cast on args so empty {} doesn't become [] after
                // the json_decode(true) → serialize → unserialize → json_encode cycle.
                $raw_args = $fc['args'] ?? [];
                $function_calls[] = [
                    'id' => $fc['id'] ?? null,
                    'name' => $fc['name'] ?? '',
                    'args' => is_array($raw_args) ? (object) $raw_args : $raw_args,
                ];
            }
        }
        
        // aichat_log_debug('[Gemini Provider] Extracted ' . count($function_calls) . ' function calls total');
        // aichat_log_debug('[Gemini Provider] Extracted function_calls: ' . json_encode($function_calls, JSON_PRETTY_PRINT));
        
        return $function_calls;
    }

    /**
     * Detect hallucinated tool execution text.
     *
     * Sometimes Gemini responds with narration like "(executing tools…)" instead
     * of emitting a proper functionCall part.  When the text is short and matches
     * known patterns we consider it a hallucination so the caller can retry.
     *
     * @param string $text The text response from the model.
     * @return bool True if the text looks like a hallucinated tool execution.
     */
    protected function is_hallucinated_tool_text( $text ) {
        $text = trim( (string) $text );
        if ( $text === '' || mb_strlen( $text ) > 200 ) {
            return false;
        }

        $patterns = [
            '/\(?\s*executing\s+tool/i',
            '/\(?\s*calling\s+tool/i',
            '/\(?\s*running\s+tool/i',
            '/\(?\s*using\s+tool/i',
            '/\(?\s*invoking\s+tool/i',
            '/ejecutando\s+herramienta/i',
            '/llamando\s+(a\s+la\s+)?herramienta/i',
            '/usando\s+herramienta/i',
            '/\*\s*executing\s+tool/i',
            '/\*\s*calling\s+tool/i',
        ];

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $text ) ) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * Build model content with function calls for conversation
     * 
     * @param array $response API response
     * @return array Message object
     */
    protected function build_model_content_with_function_calls($response) {
        return [
            'role' => 'model', // Gemini uses 'model' instead of 'assistant'
            'parts' => $response['candidates'][0]['content']['parts'] ?? []
        ];
    }
    
    /**
     * Build function response content for conversation
     * 
     * @param array $function_results Array of function execution results
     * @return array Message object
     */
    protected function build_function_response_content($function_results) {
        $parts = [];
        
        foreach ($function_results as $result) {
            $parts[] = [
                'functionResponse' => [
                    'name' => $result['function_response']['name'],
                    'response' => $result['function_response']['response']
                ]
            ];
        }
        
        return [
            'role' => 'user', // Function results come as user messages in Gemini
            'parts' => $parts
        ];
    }
    
    /**
     * Convert Gemini function_call format to format expected by trait
     * 
     * The trait expects: [['id'=>'...', 'name'=>'...', 'arguments'=>'...']]
     * NOT the OpenAI nested format with 'function' key
     * 
     * @param array $function_calls Gemini function calls
     * @return array Flat tool_calls for trait
     */
    protected function convert_gemini_function_calls_to_tool_calls($function_calls) {
        $tool_calls = [];
        
        foreach ($function_calls as $fc) {
            $raw_args = $fc['args'] ?? [];
            // Handle stdClass (from extract_function_calls cast) or array
            if ( $raw_args instanceof \stdClass ) {
                $raw_args = (array) $raw_args;
            }
            $tool_calls[] = [
                'id' => $fc['id'] ?? ('call_' . wp_generate_uuid4()),
                'name' => $fc['name'] ?? '',  // Direct access for trait
                'arguments' => wp_json_encode($raw_args)  // Direct access for trait
            ];
        }
        
        return $tool_calls;
    }

    /**
     * Accumulate usage statistics across multiple rounds.
     *
     * @param array|null $current Existing accumulator
     * @param array|null $increment Usage block to add
     * @return array|null Updated accumulator
     */
    protected function accumulate_usage($current, $increment) {
        if (!is_array($increment)) {
            return $current;
        }

        if ($current === null) {
            $current = [];
        }

        foreach (['prompt_tokens', 'completion_tokens', 'total_tokens', 'thoughts_tokens'] as $key) {
            if (isset($increment[$key])) {
                $current[$key] = ($current[$key] ?? 0) + (int) $increment[$key];
            }
        }

        return $current;
    }

    /**
     * Build a user-friendly activity label for tool execution bubbles.
     *
     * @param string $tool_name Raw tool identifier
     * @return string Friendly label for UI
     */
    protected function build_activity_label($tool_name) {
        $registered = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];

        if (!empty($tool_name) && isset($registered[$tool_name]['activity_label']) && $registered[$tool_name]['activity_label']) {
            return (string) $registered[$tool_name]['activity_label'];
        }

        switch ($tool_name) {
            case 'google_search':
            case 'googleSearch':
                return __('Running Google Search...', 'axiachat-ai');
            case 'code_execution':
            case 'codeExecution':
                return __('Running Code Execution...', 'axiachat-ai');
        }

        $fallback = $tool_name;

        if (preg_match('/^mcp_[^_]+_[^_]+_(.+)$/', (string) $fallback, $matches)) {
            $fallback = $matches[1];
        }

        $fallback = str_replace(['_', '-', '+', '.'], ' ', (string) $fallback);
        $fallback = preg_replace('/\s+/', ' ', $fallback);
        $fallback = trim($fallback);

        if ($fallback === '') {
            $fallback = __('tool', 'axiachat-ai');
        } else {
            $fallback = ucwords($fallback);
        }

    /* translators: %s: Tool name being executed (e.g., 'Get Weather') */
    return sprintf( __( 'Running %s...', 'axiachat-ai' ), $fallback );
    }
    
    /**
     * Build function response content from tool outputs (from trait)
     * 
     * @param array $tool_outputs Outputs from execute_registered_tools
     * @return array Gemini-formatted function response message
     */
    protected function build_function_response_from_outputs($tool_outputs) {
        $parts = [];
        
        foreach ($tool_outputs as $output) {
            // Extract function name from the output
            $name = $output['name'] ?? 'unknown';
            $result_content = $output['output'] ?? ''; // Trait returns 'output', not 'content'
            
            // Parse result if it's JSON
            $response_data = json_decode($result_content, true);
            if ($response_data === null) {
                // Not valid JSON, wrap as simple result
                $response_data = ['result' => $result_content];
            }
            
            $parts[] = [
                'functionResponse' => [
                    'name' => $name,
                    'response' => $response_data
                ]
            ];
        }
        
        return [
            'role' => 'user', // Function results come as user messages in Gemini
            'parts' => $parts
        ];
    }
    
    /**
     * Sanitize parts array before sending to the Gemini API.
     *
     * Fixes a PHP round-trip issue: json_decode('{}', true) returns [],
     * and json_encode([]) produces '[]' — but Gemini's functionCall.args
     * requires a JSON *object* (google.protobuf.Struct).  This method
     * casts empty args to (object) and strips any response-only fields
     * (like 'thought') that the API rejects in requests.
     *
     * @param array $parts Raw parts array (may come from a stored API response).
     * @return array Sanitized parts safe to send to Gemini.
     */
    protected function sanitize_parts_for_api( array $parts ): array {
        // Known Part fields valid in requests
        static $allowed_part_keys = [
            'text', 'inlineData', 'functionCall', 'functionResponse',
            'fileData', 'executableCode', 'codeExecutionResult', 'thought',
        ];

        $sanitized = [];
        foreach ( $parts as $part ) {
            if ( ! is_array( $part ) ) {
                $sanitized[] = $part;
                continue;
            }

            // Strip unknown top-level keys from the Part
            $clean = array_intersect_key( $part, array_flip( $allowed_part_keys ) );

            // Ensure functionCall.args is always a JSON object
            if ( isset( $clean['functionCall']['args'] ) ) {
                $args = $clean['functionCall']['args'];
                // Cast empty arrays to stdClass so json_encode produces {}
                if ( is_array( $args ) && empty( $args ) ) {
                    $clean['functionCall']['args'] = new \stdClass();
                } elseif ( is_array( $args ) ) {
                    // Force sequential-indexed arrays (edge case) to objects
                    $clean['functionCall']['args'] = (object) $args;
                }
            }

            // Ensure functionResponse.response is always a JSON object
            if ( isset( $clean['functionResponse']['response'] ) ) {
                $resp = $clean['functionResponse']['response'];
                if ( is_array( $resp ) && empty( $resp ) ) {
                    $clean['functionResponse']['response'] = new \stdClass();
                } elseif ( is_array( $resp ) && array_keys( $resp ) === range( 0, count( $resp ) - 1 ) ) {
                    // Numerically-indexed ⇒ wrap so it encodes as object
                    $clean['functionResponse']['response'] = [ 'result' => $resp ];
                }
            }

            $sanitized[] = $clean;
        }

        return $sanitized;
    }

    /**
     * Calculate cost based on usage
     * 
     * Uses pricing table for paid tier.
     * Thinking tokens (2.5 models) are included in completion_tokens count.
     * 
     * @param array $usage Usage metadata with token counts
     * @param string $model Model identifier
     * @return array Cost breakdown
     */
    public function calculate_cost($usage, $model) {
        // Get pricing for model (default to free if not in table)
        $prices = $this->pricing[$model] ?? ['input' => 0, 'output' => 0];
        
        // Calculate costs (prices are per 1M tokens)
        $input_cost = ($usage['prompt_tokens'] / 1000000) * $prices['input'];
        $output_cost = ($usage['completion_tokens'] / 1000000) * $prices['output'];
        
        // Note: thinking_tokens are already included in completion_tokens
        
        aichat_log_debug(sprintf(
            '[Gemini Provider] Cost: $%.6f input + $%.6f output = $%.6f total',
            $input_cost,
            $output_cost,
            $input_cost + $output_cost
        ));
        
        return [
            'input_cost' => $input_cost,
            'output_cost' => $output_cost,
            'total_cost' => $input_cost + $output_cost,
            'currency' => 'USD',
            'model' => $model,
            'input_tokens' => $usage['prompt_tokens'],
            'output_tokens' => $usage['completion_tokens'],
            'thinking_tokens' => $usage['thoughts_tokens'] ?? 0
        ];
    }
    
    /**
     * Get available models
     * 
     * Returns list of Gemini models supported by this provider.
     * 
     * @return array Model list with metadata
     */
    public function get_available_models() {
        // Derive from centralised model registry
        $models = [];
        if ( function_exists( 'aichat_get_models_for_provider' ) ) {
            foreach ( aichat_get_models_for_provider( 'gemini' ) as $id => $m ) {
                $models[] = [
                    'id'                => $id,
                    'name'              => $m['label'],
                    'description'       => $m['label'],
                    'context_window'    => $m['ctx'],
                    'supports_thinking' => $m['thinking'],
                    'multimodal'        => $m['multimodal'],
                ];
            }
        }
        return $models;
    }
    
    /**
     * Validate configuration
     * 
     * Checks if provider is properly configured and ready to use.
     * 
     * @return array Validation result with status and message
     */
    public function validate_config() {
        if (empty($this->api_key)) {
            return [
                'valid' => false,
                'message' => 'Gemini API key is not configured'
            ];
        }
        
        if (strlen($this->api_key) < 20) {
            return [
                'valid' => false,
                'message' => 'Gemini API key appears to be invalid (too short)'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Gemini provider is configured and ready',
            'default_model' => $this->default_model
        ];
    }
}
