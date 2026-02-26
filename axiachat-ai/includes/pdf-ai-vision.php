<?php
/**
 * PDF to Text via AI Vision
 *
 * Provides a global function to extract text from PDF pages using AI vision models.
 * Used as a fallback for PDFs where standard text extraction fails.
 *
 * @package AxiaChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Extract text from a PDF using AI Vision as fallback.
 *
 * Converts PDF pages to images and uses AI vision to extract text.
 * Useful for scanned PDFs or documents with complex layouts.
 *
 * @param string $file_path     Absolute path to the PDF file.
 * @param string $provider      AI provider to use ('openai', 'gemini', 'claude'). Default 'openai'.
 * @param array  $options       Optional settings:
 *                              - 'pages'    => array of page numbers to process, or 'all' (default 'all')
 *                              - 'max_pages'=> maximum pages to process (default 20)
 *                              - 'dpi'      => image resolution (default 150)
 * @return array|WP_Error       Array with 'text' (combined text) and 'pages' (per-page data), or WP_Error on failure.
 */
function aichat_pdf_to_text_via_ai( $file_path, $provider = 'openai', $options = [] ) {
    // Register shutdown handler to catch fatal errors during PDF processing
    $pdf_vision_processing = true;
    register_shutdown_function( function() use ( &$pdf_vision_processing, $file_path ) {
        if ( $pdf_vision_processing ) {
            $error = error_get_last();
            if ( $error && in_array( $error['type'], [ E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ], true ) ) {
                if ( function_exists( 'aichat_log_debug' ) ) {
                    aichat_log_debug( '[AIChat] PDF AI Vision: FATAL ERROR during processing', [
                        'file'    => $file_path,
                        'error'   => $error['message'],
                        'errfile' => $error['file'],
                        'errline' => $error['line'],
                    ] );
                }
            }
        }
    } );

    aichat_log_debug( '[AIChat] PDF AI Vision: start', [ 'file' => $file_path, 'provider' => $provider ] );

    if ( ! file_exists( $file_path ) ) {
        aichat_log_debug( '[AIChat] PDF AI Vision: file not found' );
        $pdf_vision_processing = false;
        return new WP_Error( 'file_not_found', __( 'PDF file not found.', 'axiachat-ai' ) );
    }

    $defaults = [
        'pages'     => 'all',
        'max_pages' => 20,
        'dpi'       => 150,
    ];
    $options = wp_parse_args( $options, $defaults );

    // Check if Imagick is available for PDF to image conversion
    if ( ! extension_loaded( 'imagick' ) ) {
        aichat_log_debug( '[AIChat] PDF AI Vision: Imagick extension not loaded' );
        return new WP_Error( 'no_imagick', __( 'ImageMagick extension is required for PDF AI vision processing.', 'axiachat-ai' ) );
    }

    aichat_log_debug( '[AIChat] PDF AI Vision: Imagick available' );

    // Check Imagick formats
    $formats = Imagick::queryFormats( 'PDF' );
    aichat_log_debug( '[AIChat] PDF AI Vision: Imagick PDF support', [ 'formats' => $formats ] );
    
    if ( empty( $formats ) ) {
        aichat_log_debug( '[AIChat] PDF AI Vision: Imagick cannot process PDFs (no Ghostscript?)' );
        return new WP_Error( 'no_pdf_support', __( 'ImageMagick cannot process PDFs. Ghostscript may not be installed.', 'axiachat-ai' ) );
    }

    // Get API key for provider
    $api_key = '';
    switch ( $provider ) {
        case 'openai':
            $api_key = aichat_get_setting( 'aichat_openai_api_key' );
            break;
        case 'claude':
            $api_key = aichat_get_setting( 'aichat_claude_api_key' );
            break;
        case 'gemini':
            $api_key = aichat_get_setting( 'aichat_gemini_api_key' );
            break;
    }

    if ( empty( $api_key ) ) {
        aichat_log_debug( '[AIChat] PDF AI Vision: no API key for provider', [ 'provider' => $provider ] );
        /* translators: %s: AI provider name (OpenAI, Claude, Gemini) */
        return new WP_Error( 'no_api_key', sprintf( __( 'API key for %s not configured.', 'axiachat-ai' ), $provider ) );
    }

    // Increase PHP limits for PDF processing
    $original_time_limit = ini_get( 'max_execution_time' );
    $original_memory_limit = ini_get( 'memory_limit' );
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary for long-running PDF processing with Imagick.
    @set_time_limit( 300 ); // 5 minutes
    // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- Necessary for memory-intensive PDF-to-image conversion.
    @ini_set( 'memory_limit', '512M' );
    
    aichat_log_debug( '[AIChat] PDF AI Vision: PHP limits adjusted', [
        'time_limit'   => '300s (was: ' . $original_time_limit . ')',
        'memory_limit' => '512M (was: ' . $original_memory_limit . ')',
    ] );

    // Force log flush
    if ( function_exists( 'error_log' ) ) {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional debug logging for PDF processing diagnostics.
        error_log( '[AIChat] PDF AI Vision: starting Imagick processing for ' . basename( $file_path ) );
    }

    try {
        aichat_log_debug( '[AIChat] PDF AI Vision: creating Imagick instance' );
        $imagick = new Imagick();
        
        aichat_log_debug( '[AIChat] PDF AI Vision: setting resolution' );
        $imagick->setResolution( $options['dpi'], $options['dpi'] );
        
        // Set resource limits to prevent hangs
        aichat_log_debug( '[AIChat] PDF AI Vision: setting resource limits' );
        try {
            $imagick->setResourceLimit( Imagick::RESOURCETYPE_TIME, 120 ); // 2 min max
            $imagick->setResourceLimit( Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024 ); // 256MB
            aichat_log_debug( '[AIChat] PDF AI Vision: resource limits set OK' );
        } catch ( Exception $re ) {
            aichat_log_debug( '[AIChat] PDF AI Vision: could not set resource limits (non-fatal)', [ 'error' => $re->getMessage() ] );
        }

        aichat_log_debug( '[AIChat] PDF AI Vision: about to call readImage', [ 'path' => $file_path ] );
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostic logging for Imagick hang detection.
        error_log( '[AIChat] PDF AI Vision: calling readImage NOW - if no more logs appear, Imagick is hanging' );

        // Try to read the PDF - this can fail for secured/encrypted PDFs
        try {
            // Add [0] to read only first page initially (faster check if PDF is readable)
            $test_path = $file_path . '[0]';
            aichat_log_debug( '[AIChat] PDF AI Vision: testing first page', [ 'test_path' => $test_path ] );
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostic logging for Imagick hang detection.
            error_log( '[AIChat] PDF AI Vision: about to read first page [0]' );
            
            $imagick->readImage( $test_path );
            
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostic logging for Imagick processing.
            error_log( '[AIChat] PDF AI Vision: first page read OK!' );
            aichat_log_debug( '[AIChat] PDF AI Vision: first page read successfully' );
            
            // Clear and read all pages now
            $imagick->clear();
            $imagick->setResolution( $options['dpi'], $options['dpi'] );
            
            aichat_log_debug( '[AIChat] PDF AI Vision: reading all pages' );
            $imagick->readImage( $file_path );
            aichat_log_debug( '[AIChat] PDF AI Vision: all pages read successfully' );
            
        } catch ( ImagickException $ie ) {
            $error_msg = $ie->getMessage();
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Exception logging for PDF processing diagnostics.
            error_log( '[AIChat] PDF AI Vision: CAUGHT ImagickException: ' . $error_msg );
            aichat_log_debug( '[AIChat] PDF AI Vision: Imagick readImage failed', [
                'error'   => $error_msg,
                'code'    => $ie->getCode(),
                'file'    => $ie->getFile(),
                'line'    => $ie->getLine(),
            ] );
            
            // Check for common issues
            if ( stripos( $error_msg, 'security policy' ) !== false ) {
                return new WP_Error( 'security_policy', __( 'ImageMagick security policy blocks PDF processing. Contact your hosting provider.', 'axiachat-ai' ) );
            }
            if ( stripos( $error_msg, 'password' ) !== false || stripos( $error_msg, 'encrypt' ) !== false ) {
                return new WP_Error( 'pdf_encrypted', __( 'PDF is encrypted/password protected and cannot be processed.', 'axiachat-ai' ) );
            }
            
            return new WP_Error( 'imagick_read_error', $error_msg );
        }

        $total_pages = $imagick->getNumberImages();
        aichat_log_debug( '[AIChat] PDF AI Vision: PDF loaded', [ 'total_pages' => $total_pages ] );

        $pages_to_process = [];

        if ( 'all' === $options['pages'] ) {
            $pages_to_process = range( 0, min( $total_pages - 1, $options['max_pages'] - 1 ) );
        } elseif ( is_array( $options['pages'] ) ) {
            foreach ( $options['pages'] as $p ) {
                $idx = (int) $p - 1; // Convert 1-based to 0-based
                if ( $idx >= 0 && $idx < $total_pages && $idx < $options['max_pages'] ) {
                    $pages_to_process[] = $idx;
                }
            }
        }

        aichat_log_debug( '[AIChat] PDF AI Vision: pages to process', [ 'count' => count( $pages_to_process ) ] );

        $results = [
            'text'        => '',
            'pages'       => [],
            'total_pages' => $total_pages,
            'processed'   => count( $pages_to_process ),
        ];

        foreach ( $pages_to_process as $page_idx ) {
            aichat_log_debug( '[AIChat] PDF AI Vision: processing page', [ 'page' => $page_idx + 1 ] );

            $imagick->setIteratorIndex( $page_idx );
            $imagick->setImageFormat( 'png' );
            
            // Convert to base64
            $image_data = $imagick->getImageBlob();
            $base64_image = base64_encode( $image_data );

            aichat_log_debug( '[AIChat] PDF AI Vision: page converted to image', [ 'page' => $page_idx + 1, 'image_size' => strlen( $image_data ) ] );
            
            // Send to AI for text extraction
            $page_text = aichat_extract_text_from_image( $base64_image, $provider, $api_key );
            
            if ( is_wp_error( $page_text ) ) {
                aichat_log_debug( '[AIChat] AI Vision page extraction failed', [
                    'page'  => $page_idx + 1,
                    'error' => $page_text->get_error_message(),
                ] );
                continue;
            }

            aichat_log_debug( '[AIChat] PDF AI Vision: page text extracted', [ 'page' => $page_idx + 1, 'text_len' => strlen( $page_text ) ] );

            $results['pages'][] = [
                'page' => $page_idx + 1,
                'text' => $page_text,
            ];
            $results['text'] .= "--- Page " . ( $page_idx + 1 ) . " ---\n" . $page_text . "\n\n";
        }

        $imagick->clear();
        $imagick->destroy();

        aichat_log_debug( '[AIChat] PDF AI Vision: complete', [ 'total_text_len' => strlen( $results['text'] ), 'pages_extracted' => count( $results['pages'] ) ] );

        return $results;

    } catch ( Exception $e ) {
        aichat_log_debug( '[AIChat] PDF AI Vision: Imagick exception', [ 'error' => $e->getMessage() ] );
        return new WP_Error( 'imagick_error', $e->getMessage() );
    }
}

/**
 * Extract text from a base64-encoded image using AI Vision.
 *
 * @param string $base64_image  Base64-encoded image data.
 * @param string $provider      AI provider ('openai', 'gemini', 'claude').
 * @param string $api_key       API key for the provider.
 * @return string|WP_Error      Extracted text or WP_Error on failure.
 */
function aichat_extract_text_from_image( $base64_image, $provider, $api_key ) {
    $prompt = 'Extract all the text content from this image. Maintain the original structure and formatting as much as possible. If there are tables, preserve the column structure. Only return the extracted text, no additional commentary.';

    switch ( $provider ) {
        case 'openai':
            return aichat_ai_vision_openai( $base64_image, $prompt, $api_key );
        case 'gemini':
            return aichat_ai_vision_gemini( $base64_image, $prompt, $api_key );
        case 'claude':
            return aichat_ai_vision_claude( $base64_image, $prompt, $api_key );
        default:
            return new WP_Error( 'unsupported_provider', __( 'Unsupported AI provider for vision.', 'axiachat-ai' ) );
    }
}

/**
 * OpenAI Vision API call.
 */
function aichat_ai_vision_openai( $base64_image, $prompt, $api_key ) {
    // Use gpt-4o for best vision capabilities (can be filtered)
    $model = apply_filters( 'aichat_vision_openai_model', 'gpt-4o' );

    aichat_log_debug( '[AIChat] OpenAI Vision: sending request', [ 'model' => $model, 'image_size' => strlen( $base64_image ) ] );

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
        ],
        'body'    => wp_json_encode( [
            'model'      => $model,
            'max_tokens' => 4096,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                        [
                            'type'      => 'image_url',
                            'image_url' => [
                                'url' => 'data:image/png;base64,' . $base64_image,
                            ],
                        ],
                    ],
                ],
            ],
        ] ),
        'timeout' => 90,
    ] );

    if ( is_wp_error( $response ) ) {
        aichat_log_debug( '[AIChat] OpenAI Vision: WP error', [ 'error' => $response->get_error_message() ] );
        return $response;
    }

    $http_code = wp_remote_retrieve_response_code( $response );
    $body_raw  = wp_remote_retrieve_body( $response );
    $body      = json_decode( $body_raw, true );

    // Log raw response (truncated for large responses)
    aichat_log_debug( '[AIChat] OpenAI Vision: raw response', [
        'http_code'    => $http_code,
        'body_preview' => substr( $body_raw, 0, 1000 ),
    ] );

    if ( isset( $body['error'] ) ) {
        aichat_log_debug( '[AIChat] OpenAI Vision: API error', [ 'error' => $body['error']['message'] ?? 'Unknown' ] );
        return new WP_Error( 'openai_error', $body['error']['message'] ?? 'Unknown OpenAI error' );
    }

    $text = $body['choices'][0]['message']['content'] ?? '';
    aichat_log_debug( '[AIChat] OpenAI Vision: success', [ 'text_len' => strlen( $text ) ] );

    return $text;
}

/**
 * Gemini Vision API call.
 */
function aichat_ai_vision_gemini( $base64_image, $prompt, $api_key ) {
    $endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . rawurlencode( $api_key );

    $response = wp_remote_post( $endpoint, [
        'headers' => [ 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'contents' => [
                [
                    'parts' => [
                        [ 'text' => $prompt ],
                        [
                            'inline_data' => [
                                'mime_type' => 'image/png',
                                'data'      => $base64_image,
                            ],
                        ],
                    ],
                ],
            ],
        ] ),
        'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['error'] ) ) {
        return new WP_Error( 'gemini_error', $body['error']['message'] ?? 'Unknown Gemini error' );
    }

    return $body['candidates'][0]['content']['parts'][0]['text'] ?? '';
}

/**
 * Claude Vision API call.
 */
function aichat_ai_vision_claude( $base64_image, $prompt, $api_key ) {
    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'headers' => [
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ],
        'body'    => wp_json_encode( [
            'model'      => 'claude-sonnet-4-20250514',
            'max_tokens' => 4096,
            'messages'   => [
                [
                    'role'    => 'user',
                    'content' => [
                        [
                            'type'   => 'image',
                            'source' => [
                                'type'       => 'base64',
                                'media_type' => 'image/png',
                                'data'       => $base64_image,
                            ],
                        ],
                        [
                            'type' => 'text',
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ] ),
        'timeout' => 60,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( isset( $body['error'] ) ) {
        return new WP_Error( 'claude_error', $body['error']['message'] ?? 'Unknown Claude error' );
    }

    return $body['content'][0]['text'] ?? '';
}

/**
 * Check if AI Vision PDF processing is available.
 *
 * @return array Status information.
 */
function aichat_pdf_ai_vision_status() {
    $status = [
        'available'    => false,
        'imagick'      => extension_loaded( 'imagick' ),
        'has_provider' => false,
        'providers'    => [],
    ];

    // Check which providers are configured
    if ( function_exists( 'aichat_get_setting' ) ) {
        if ( ! empty( aichat_get_setting( 'aichat_openai_api_key' ) ) ) {
            $status['providers'][] = 'openai';
            $status['has_provider'] = true;
        }
        if ( ! empty( aichat_get_setting( 'aichat_gemini_api_key' ) ) ) {
            $status['providers'][] = 'gemini';
            $status['has_provider'] = true;
        }
        if ( ! empty( aichat_get_setting( 'aichat_claude_api_key' ) ) ) {
            $status['providers'][] = 'claude';
            $status['has_provider'] = true;
        }
    }

    $status['available'] = $status['imagick'] && $status['has_provider'];

    return $status;
}
