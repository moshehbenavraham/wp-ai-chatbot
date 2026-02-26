<?php
/**
 * AIChat – Centralised Model Registry
 *
 * Single source of truth for every chat-completion model the plugin supports.
 * All consumers (admin JS, pricing, provider adapters, AJAX handlers, wizard)
 * pull data from these helpers instead of keeping their own hard-coded lists.
 *
 * @package AIChat
 * @since   2.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * 1. CANONICAL REGISTRY
 * ========================================================================= */

/**
 * Return the full model registry (filterable).
 *
 * Structure per entry:
 *   id             string  Canonical model identifier sent to the API.
 *   provider       string  'openai' | 'anthropic' | 'gemini'
 *   label          string  Human-readable name (shown in selects).
 *   tags           array   Combinable: 'new','recommended','fastest','efficient','advanced','legacy'
 *   ctx            int     Context-window size (tokens).
 *   max_out        int     Hard max output tokens.
 *   rec_out        int     Recommended max_tokens setting.
 *   thinking       bool    Whether the model supports thinking/reasoning.
 *   multimodal     bool    Image / vision capable.
 *   pricing        array   { input: $/1M tokens, output: $/1M tokens }
 *   aliases        array   Short-hand / undated aliases that resolve to this id.
 *   deprecated     array   Previously-valid ids that should redirect here.
 *   is_default     bool    Whether this is the provider's recommended default.
 *   fallback_order int|null  Lower = tried first in fallback chains  (null = not in chain).
 *
 * @return array[] Indexed by canonical model id.
 */
function aichat_get_model_registry() {
    static $cache = null;
    if ( null !== $cache ) {
        return $cache;
    }

    $registry = [

        /* ==============================================================
         *  OPENAI
         * ============================================================== */

        // GPT-5.2 (Dec 2025)
        'gpt-5.2-chat-latest' => [
            'provider'       => 'openai',
            'label'          => 'GPT-5.2 Instant (Dec 2025)',
            'tags'           => [ 'recommended' ],
            'ctx'            => 256000,
            'max_out'        => 65536,
            'rec_out'        => 32768,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 1.75, 'output' => 14.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => true,
            'fallback_order' => null,
        ],
        'gpt-5.2' => [
            'provider'       => 'openai',
            'label'          => 'GPT-5.2 Thinking (Adaptive Reasoning)',
            'tags'           => [],
            'ctx'            => 256000,
            'max_out'        => 65536,
            'rec_out'        => 32768,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 1.75, 'output' => 14.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // GPT-5 (2025)
        'gpt-5' => [
            'provider'       => 'openai',
            'label'          => 'GPT-5',
            'tags'           => [],
            'ctx'            => 256000,
            'max_out'        => 32768,
            'rec_out'        => 32768,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 1.25, 'output' => 10.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gpt-5-mini' => [
            'provider'       => 'openai',
            'label'          => 'GPT-5 Mini',
            'tags'           => [],
            'ctx'            => 128000,
            'max_out'        => 16384,
            'rec_out'        => 12000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.25, 'output' => 2.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gpt-5-nano' => [
            'provider'       => 'openai',
            'label'          => 'GPT-5 Nano',
            'tags'           => [],
            'ctx'            => 64000,
            'max_out'        => 8192,
            'rec_out'        => 6000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.05, 'output' => 0.40 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // GPT-4.1 family
        'gpt-4.1' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4.1',
            'tags'           => [],
            'ctx'            => 1047576,
            'max_out'        => 32768,
            'rec_out'        => 16384,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 2.00, 'output' => 8.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gpt-4.1-mini' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4.1 Mini',
            'tags'           => [],
            'ctx'            => 1047576,
            'max_out'        => 32768,
            'rec_out'        => 12000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.40, 'output' => 1.60 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gpt-4.1-nano' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4.1 Nano',
            'tags'           => [],
            'ctx'            => 1047576,
            'max_out'        => 32768,
            'rec_out'        => 8000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.10, 'output' => 0.40 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // GPT-4o (2024)
        'gpt-4o' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4o',
            'tags'           => [],
            'ctx'            => 128000,
            'max_out'        => 16384,
            'rec_out'        => 16384,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 2.50, 'output' => 10.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gpt-4o-mini' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4o Mini',
            'tags'           => [],
            'ctx'            => 128000,
            'max_out'        => 12288,
            'rec_out'        => 8000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.15, 'output' => 0.60 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // GPT-4 Turbo (legacy)
        'gpt-4-turbo' => [
            'provider'       => 'openai',
            'label'          => 'GPT-4 Turbo',
            'tags'           => [ 'legacy' ],
            'ctx'            => 128000,
            'max_out'        => 4096,
            'rec_out'        => 3500,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 10.00, 'output' => 30.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // GPT-3.5 (legacy)
        'gpt-3.5-turbo' => [
            'provider'       => 'openai',
            'label'          => 'GPT-3.5 Turbo',
            'tags'           => [ 'legacy' ],
            'ctx'            => 16384,
            'max_out'        => 4096,
            'rec_out'        => 3000,
            'thinking'       => false,
            'multimodal'     => false,
            'pricing'        => [ 'input' => 0.50, 'output' => 1.50 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        /* ==============================================================
         *  ANTHROPIC (Claude)
         * ============================================================== */

        // Claude 4.6 (Feb 2026)
        'claude-opus-4-6' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Opus 4.6 (Feb 2026)',
            'tags'           => [ 'new' ],
            'ctx'            => 200000,
            'max_out'        => 32000,
            'rec_out'        => 25000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 15.00, 'output' => 75.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // Claude 4.5 (2025)
        'claude-sonnet-4-5-20250929' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Sonnet 4.5',
            'tags'           => [ 'recommended' ],
            'ctx'            => 1000000,
            'max_out'        => 64000,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 3.00, 'output' => 15.00 ],
            'aliases'        => [ 'claude-sonnet-4-5' ],
            'deprecated'     => [
                'claude-3-5-sonnet',
                'claude-3-5-sonnet-latest',
                'claude-3-5-sonnet-20241022',
                'claude-3-5-sonnet-20240620',
                'claude-3-sonnet',
                'claude-3-sonnet-20240229',
            ],
            'is_default'     => true,
            'fallback_order' => 1,
        ],
        'claude-haiku-4-5-20251001' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Haiku 4.5',
            'tags'           => [ 'fastest' ],
            'ctx'            => 1000000,
            'max_out'        => 64000,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 1.00, 'output' => 5.00 ],
            'aliases'        => [ 'claude-haiku-4-5' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => 2,
        ],
        'claude-opus-4-5-20251101' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Opus 4.5',
            'tags'           => [],
            'ctx'            => 200000,
            'max_out'        => 32000,
            'rec_out'        => 25000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 5.00, 'output' => 25.00 ],
            'aliases'        => [ 'claude-opus-4-5' ],
            'deprecated'     => [
                'claude-3-opus',
                'claude-3-opus-20240229',
                'claude-3-5-opus',
            ],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // Claude 4 / 4.1 (2025)
        'claude-sonnet-4-20250514' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Sonnet 4',
            'tags'           => [],
            'ctx'            => 200000,
            'max_out'        => 64000,
            'rec_out'        => 25000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 3.00, 'output' => 15.00 ],
            'aliases'        => [ 'claude-sonnet-4' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'claude-opus-4-1-20250805' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Opus 4.1',
            'tags'           => [],
            'ctx'            => 200000,
            'max_out'        => 32000,
            'rec_out'        => 25000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 15.00, 'output' => 75.00 ],
            'aliases'        => [ 'claude-opus-4-1' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'claude-opus-4-20250514' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Opus 4',
            'tags'           => [],
            'ctx'            => 200000,
            'max_out'        => 32000,
            'rec_out'        => 25000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 15.00, 'output' => 75.00 ],
            'aliases'        => [ 'claude-opus-4' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // Claude 3.x (legacy)
        'claude-3-7-sonnet-20250219' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Sonnet 3.7',
            'tags'           => [ 'legacy' ],
            'ctx'            => 200000,
            'max_out'        => 8192,
            'rec_out'        => 6000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 3.00, 'output' => 15.00 ],
            'aliases'        => [ 'claude-3-7-sonnet' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'claude-3-5-haiku-20241022' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Haiku 3.5',
            'tags'           => [ 'legacy' ],
            'ctx'            => 200000,
            'max_out'        => 8192,
            'rec_out'        => 6000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.80, 'output' => 4.00 ],
            'aliases'        => [ 'claude-3-5-haiku', 'claude-3-5-haiku-latest' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => 3,
        ],
        'claude-3-haiku-20240307' => [
            'provider'       => 'anthropic',
            'label'          => 'Claude Haiku 3',
            'tags'           => [ 'legacy' ],
            'ctx'            => 200000,
            'max_out'        => 4096,
            'rec_out'        => 3000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.25, 'output' => 1.25 ],
            'aliases'        => [ 'claude-3-haiku' ],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => 4,
        ],

        /* ==============================================================
         *  GEMINI
         * ============================================================== */

        // Gemini 3 (preview)
        'gemini-3-pro-preview' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 3 Pro Preview',
            'tags'           => [ 'new', 'advanced' ],
            'ctx'            => 1000000,
            'max_out'        => 65536,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.50, 'output' => 2.00 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gemini-3-flash-preview' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 3 Flash Preview',
            'tags'           => [ 'new' ],
            'ctx'            => 1000000,
            'max_out'        => 65536,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.35, 'output' => 1.40 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // Gemini 2.5 (stable)
        'gemini-2.5-pro' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 2.5 Pro (Reasoning)',
            'tags'           => [],
            'ctx'            => 1000000,
            'max_out'        => 65536,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.35, 'output' => 1.40 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],
        'gemini-2.5-flash' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 2.5 Flash (Balanced)',
            'tags'           => [ 'recommended' ],
            'ctx'            => 1000000,
            'max_out'        => 65536,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.30, 'output' => 1.20 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => true,
            'fallback_order' => 1,
        ],
        'gemini-2.5-flash-lite' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 2.5 Flash-Lite (Fast)',
            'tags'           => [ 'efficient' ],
            'ctx'            => 1000000,
            'max_out'        => 65536,
            'rec_out'        => 50000,
            'thinking'       => true,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.075, 'output' => 0.30 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => null,
        ],

        // Gemini 2.0 (legacy)
        'gemini-2.0-flash' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 2.0 Flash',
            'tags'           => [ 'legacy' ],
            'ctx'            => 1000000,
            'max_out'        => 8192,
            'rec_out'        => 6000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.30, 'output' => 1.20 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => 2,
        ],
        'gemini-2.0-flash-lite' => [
            'provider'       => 'gemini',
            'label'          => 'Gemini 2.0 Flash-Lite',
            'tags'           => [ 'legacy' ],
            'ctx'            => 1000000,
            'max_out'        => 8192,
            'rec_out'        => 6000,
            'thinking'       => false,
            'multimodal'     => true,
            'pricing'        => [ 'input' => 0.075, 'output' => 0.30 ],
            'aliases'        => [],
            'deprecated'     => [],
            'is_default'     => false,
            'fallback_order' => 3,
        ],
    ];

    /**
     * Filter the model registry so add-ons / site admins can add or override models.
     *
     * @since 2.4.0
     * @param array $registry Keyed by canonical model id.
     */
    $cache = apply_filters( 'aichat_model_registry', $registry );
    return $cache;
}

/* =========================================================================
 * 2. ALIAS → CANONICAL RESOLVER
 * ========================================================================= */

/**
 * Build (and cache) a flat alias→canonical lookup from the registry.
 * Includes: explicit 'aliases' + 'deprecated' entries.
 *
 * @return array [ alias_lowercase => canonical_id ]
 */
function aichat_build_alias_map() {
    static $map = null;
    if ( null !== $map ) {
        return $map;
    }
    $map = [];
    foreach ( aichat_get_model_registry() as $id => $m ) {
        // aliases
        if ( ! empty( $m['aliases'] ) ) {
            foreach ( (array) $m['aliases'] as $a ) {
                $map[ strtolower( $a ) ] = $id;
            }
        }
        // deprecated redirects
        if ( ! empty( $m['deprecated'] ) ) {
            foreach ( (array) $m['deprecated'] as $d ) {
                $map[ strtolower( $d ) ] = $id;
            }
        }
    }
    return $map;
}

/**
 * Resolve any model string to its canonical id.
 *
 * 1. If the string matches a canonical id exactly → return it.
 * 2. If it matches an alias or deprecated key → return the canonical target.
 * 3. Prefix fallback: try matching by longest prefix in the same provider.
 * 4. Otherwise → return the provider default (or global default).
 *
 * @param string      $model    The raw model string.
 * @param string|null $provider Optional provider hint ('openai','anthropic','gemini','claude').
 * @return string Canonical model id.
 */
function aichat_resolve_model( $model, $provider = null ) {
    $m   = strtolower( trim( (string) $model ) );
    $reg = aichat_get_model_registry();

    // Normalise "claude" provider alias
    if ( $provider === 'claude' ) {
        $provider = 'anthropic';
    }

    // 1. Exact canonical match
    if ( isset( $reg[ $m ] ) ) {
        return $m;
    }

    // 2. Alias / deprecated
    $alias_map = aichat_build_alias_map();
    if ( isset( $alias_map[ $m ] ) ) {
        return $alias_map[ $m ];
    }

    // 3. Prefix fallback (longest match within same provider)
    $best      = '';
    $best_id   = '';
    foreach ( $reg as $id => $entry ) {
        if ( $provider && $entry['provider'] !== $provider ) {
            continue;
        }
        if ( stripos( $m, $id ) === 0 && strlen( $id ) > strlen( $best ) ) {
            $best    = $id;
            $best_id = $id;
        }
    }
    if ( $best_id !== '' ) {
        return $best_id;
    }

    // 4. Provider default
    return aichat_get_default_model( $provider );
}

/* =========================================================================
 * 3. CONVENIENCE GETTERS
 * ========================================================================= */

/**
 * Get models for a specific provider, in display order.
 *
 * @param string $provider 'openai' | 'anthropic' | 'claude' | 'gemini'
 * @return array[] Subset of the registry for that provider.
 */
function aichat_get_models_for_provider( $provider ) {
    if ( $provider === 'claude' ) {
        $provider = 'anthropic';
    }
    $out = [];
    foreach ( aichat_get_model_registry() as $id => $m ) {
        if ( $m['provider'] === $provider ) {
            $out[ $id ] = $m;
        }
    }
    return $out;
}

/**
 * Return the default (recommended) model id for a provider.
 *
 * @param string|null $provider Provider key (null → 'openai').
 * @return string
 */
function aichat_get_default_model( $provider = null ) {
    if ( $provider === 'claude' ) {
        $provider = 'anthropic';
    }
    if ( ! $provider ) {
        $provider = 'openai';
    }
    foreach ( aichat_get_model_registry() as $id => $m ) {
        if ( $m['provider'] === $provider && ! empty( $m['is_default'] ) ) {
            return $id;
        }
    }
    // Hard fallbacks in case filter removed defaults
    $fallbacks = [
        'openai'    => 'gpt-5.2-chat-latest',
        'anthropic' => 'claude-sonnet-4-5-20250929',
        'gemini'    => 'gemini-2.5-flash',
    ];
    return $fallbacks[ $provider ] ?? 'gpt-5.2-chat-latest';
}

/**
 * Build the fallback chain for a provider (sorted by fallback_order).
 *
 * @param string $provider Provider key.
 * @return string[] Canonical model ids in fallback order.
 */
function aichat_get_fallback_chain( $provider ) {
    if ( $provider === 'claude' ) {
        $provider = 'anthropic';
    }
    $chain = [];
    foreach ( aichat_get_model_registry() as $id => $m ) {
        if ( $m['provider'] === $provider && $m['fallback_order'] !== null ) {
            $chain[ $m['fallback_order'] ] = $id;
        }
    }
    ksort( $chain );
    return array_values( $chain );
}

/**
 * Get pricing for a model (per 1M tokens).
 *
 * @param string $model   Canonical or alias model id.
 * @param string $provider Provider hint (optional).
 * @return array|null { input: float, output: float } or null if unknown.
 */
function aichat_get_model_pricing( $model, $provider = null ) {
    $canonical = aichat_resolve_model( $model, $provider );
    $reg       = aichat_get_model_registry();
    return $reg[ $canonical ]['pricing'] ?? null;
}

/**
 * Get token limits for a model.
 *
 * @param string $model   Canonical or alias model id.
 * @param string $provider Provider hint (optional).
 * @return array|null { ctx: int, max_out: int, rec_out: int } or null.
 */
function aichat_get_model_limits( $model, $provider = null ) {
    $canonical = aichat_resolve_model( $model, $provider );
    $reg       = aichat_get_model_registry();
    if ( ! isset( $reg[ $canonical ] ) ) {
        return null;
    }
    $m = $reg[ $canonical ];
    return [
        'ctx'     => $m['ctx'],
        'max_out' => $m['max_out'],
        'rec_out' => $m['rec_out'],
    ];
}

/* =========================================================================
 * 4. PRICING BRIDGE  (backward-compat for aichat_model_pricing / aichat_calc_cost_micros)
 * ========================================================================= */

/**
 * Build the legacy pricing table (per 1K tokens) from the registry.
 * Used by usage-functions.php via the 'aichat_model_pricing' filter or direct call.
 *
 * @return array [ provider => [ model_prefix => [ input_per_1k, output_per_1k ] ] ]
 */
function aichat_registry_build_legacy_pricing() {
    $reg    = aichat_get_model_registry();
    $out    = [];

    foreach ( $reg as $id => $m ) {
        $prov = $m['provider'];
        // Convert $/1M → $/1K
        $entry = [
            'input_per_1k'  => $m['pricing']['input']  / 1000.0,
            'output_per_1k' => $m['pricing']['output'] / 1000.0,
        ];
        $out[ $prov ][ $id ] = $entry;

        // For aliases, add prefix-based entries (e.g. 'claude-sonnet-4-5')
        if ( ! empty( $m['aliases'] ) ) {
            foreach ( (array) $m['aliases'] as $a ) {
                $out[ $prov ][ $a ] = $entry;
            }
        }
    }

    // Backward-compat: 'claude' is an alias for 'anthropic'
    if ( isset( $out['anthropic'] ) ) {
        $out['claude'] = $out['anthropic'];
    }

    // Add static embedding models (these are infrastructure, not chat)
    $out['openai']['text-embedding-3-small'] = [ 'input_per_1k' => 0.00002, 'output_per_1k' => 0.00002 ];
    $out['openai']['text-embedding-3-large'] = [ 'input_per_1k' => 0.00013, 'output_per_1k' => 0.00013 ];

    return $out;
}

/* =========================================================================
 * 5. JS PAYLOAD  (for wp_localize_script)
 * ========================================================================= */

/**
 * Build a compact JS-friendly payload suitable for wp_localize_script.
 *
 * Grouped by provider, preserves display order, includes tag labels for the dropdown.
 *
 * @return array Associative array ready to be JSON-encoded.
 */
function aichat_registry_js_payload() {
    $reg     = aichat_get_model_registry();
    $models  = [];
    $tokens  = [];
    $defaults = [];

    // Tag → select label suffix
    $tag_labels = [
        'new'         => '[NEW]',
        'recommended' => '[RECOMMENDED]',
        'fastest'     => '[FASTEST]',
        'efficient'   => '[EFFICIENT]',
        'advanced'    => '[ADVANCED]',
        'legacy'      => 'Legacy:',
    ];

    foreach ( $reg as $id => $m ) {
        $prov = $m['provider'];

        // Build select label
        $label = $m['label'];
        $prefix = '';
        $suffix = '';
        foreach ( $m['tags'] as $t ) {
            if ( $t === 'legacy' ) {
                $prefix = 'Legacy: ';
            } elseif ( isset( $tag_labels[ $t ] ) ) {
                $suffix .= ' ' . $tag_labels[ $t ];
            }
        }
        $select_label = $prefix . ( $prefix ? preg_replace( '/^Legacy:\s*/i', '', $label ) : $label ) . $suffix;

        $models[ $prov ][] = [
            'val'   => $id,
            'label' => $select_label,
        ];

        $tokens[ $id ] = [
            'ctx'  => $m['ctx'],
            'comp' => $m['max_out'],
            'rec'  => $m['rec_out'],
        ];

        if ( ! empty( $m['is_default'] ) ) {
            $defaults[ $prov ] = $id;
        }
    }

    // Normalise provider key for Claude (JS uses 'anthropic')
    // No renaming needed — provider is already 'anthropic' in registry.

    return [
        'models'   => $models,   // { openai: [{val,label},...], anthropic: [...], gemini: [...] }
        'tokens'   => $tokens,   // { "model-id": {ctx,comp,rec}, ... }
        'defaults' => $defaults, // { openai: "id", anthropic: "id", gemini: "id" }
    ];
}
