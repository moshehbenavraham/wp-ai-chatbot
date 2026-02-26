<?php
/**
 * AI Chat — Core (frontend/global widget)
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'AIChat_Core' ) ) {

    class AIChat_Core {

        /** Instancia singleton */
        private static $instance = null;

        /** Evita doble render en el mismo request */
        private static $rendered_global = false;

        /** Evita duplicar la variable AIChatVars */
        private static $vars_localized  = false;

        /** Obtiene instancia única */
        public static function instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            aichat_log_debug('[AIChat Core] __construct: hooks');
            add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
            add_action( 'wp_footer', [ $this, 'maybe_render_global_widget' ], 5 );
        }

        /**
         * Registra (no encola) los assets del frontend.
         */
        public function register_assets() {
            aichat_log_debug('[AIChat Core] register_assets: start');

            // Calcula desde la raíz del plugin (más robusto)
            $base_path = dirname( plugin_dir_path( __FILE__ ) ) . '/';
            $base_url  = dirname( plugin_dir_url( __FILE__ ) ) . '/';
            aichat_log_debug('[AIChat Core] register_assets: base_path=' . $base_path);
            aichat_log_debug('[AIChat Core] register_assets: base_url=' . $base_url);

            $css_url = $base_url . 'assets/css/aichat-frontend.css';
            $js_url  = $base_url . 'assets/js/aichat-frontend.js';

            $css_path = $base_path . 'assets/css/aichat-frontend.css';
            $js_path  = $base_path . 'assets/js/aichat-frontend.js';

            $ver_css  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : '1.0.0';
            $ver_js   = file_exists( $js_path )  ? (string) filemtime( $js_path )  : '1.0.0';

            aichat_log_debug('[AIChat Core] register_assets: css_url=' . $css_url . ' exists=' . ( file_exists($css_path) ? '1' : '0' ) . ' ver=' . $ver_css);
            aichat_log_debug('[AIChat Core] register_assets: js_url='  . $js_url  . ' exists=' . ( file_exists($js_path)  ? '1' : '0' ) . ' ver=' . $ver_js);

            // Registrar estilos y scripts (script depende de jQuery and wp-i18n for translations)
            wp_register_style( 'aichat-frontend', $css_url, [], $ver_css );
            wp_register_script( 'aichat-frontend', $js_url, ['jquery', 'wp-i18n'], $ver_js, true );
            
            // Set script translations for frontend i18n
            wp_set_script_translations( 'aichat-frontend', 'axiachat-ai', $base_path . 'languages' );

            aichat_log_debug('[AIChat Core] register_assets: style registered=' . ( wp_style_is('aichat-frontend','registered') ? '1' : '0' ));
            aichat_log_debug('[AIChat Core] register_assets: script registered=' . ( wp_script_is('aichat-frontend','registered') ? '1' : '0' ));

            // Variables comunes para cualquier instancia (shortcode o global) — SOLO una vez
            if ( ! self::$vars_localized ) {
                wp_localize_script( 'aichat-frontend', 'AIChatVars', [
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'aichat_ajax' ),
                    'page_id'  => get_queried_object_id(),
                ] );
                self::$vars_localized = true;
                aichat_log_debug('[AIChat Core] register_assets: localized AIChatVars (once)');
            } else {
                aichat_log_debug('[AIChat Core] register_assets: AIChatVars already localized, skipping');
            }
        }

        /** Devuelve el primer bot por si el slug global está vacío (fallback opcional) */
        private function get_first_bot_slug() {
            global $wpdb;
            $bots_table = $wpdb->prefix . 'aichat_bots';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal table lookup; small read; safe fallback.
            $slug = $wpdb->get_var( "SELECT slug FROM {$bots_table} ORDER BY id ASC LIMIT 1" );
            aichat_log_debug('[AIChat Core] get_first_bot_slug: ' . ( $slug ?: 'NULL' ));
            return $slug;
        }

        /** Helper para coger el primer campo no vacío de la fila del bot */
        private function pick( array $row, array $keys, $default = '' ) {
            foreach ( $keys as $k ) {
                if ( isset( $row[ $k ] ) && $row[ $k ] !== '' && $row[ $k ] !== null ) {
                    return is_string( $row[ $k ] ) ? wp_unslash( $row[ $k ] ) : $row[ $k ];
                }
            }
            return $default;
        }

        /**
         * Pinta el widget GLOBAL en el footer si procede, con UI del bot desde BD.
         */
        public function maybe_render_global_widget() {
            aichat_log_debug('[AIChat Core] maybe_render_global_widget: start');

            if ( self::$rendered_global ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort already rendered');
                return;
            }
            self::$rendered_global = true;

            if ( is_admin() ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort is_admin');
                return;
            }
            if ( is_feed() ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort is_feed');
                return;
            }
            if ( wp_doing_ajax() ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort wp_doing_ajax');
                return;
            }

            if ( ! empty( $GLOBALS['aichat_has_shortcode'] ) ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort has_shortcode');
                return;
            }

            $enabled = (bool) get_option( 'aichat_global_bot_enabled', false );
            aichat_log_debug('[AIChat Core] maybe_render_global_widget: enabled=' . ( $enabled ? '1' : '0' ));
            if ( ! $enabled ) {
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort not enabled');
                return;
            }

            $slug = get_option( 'aichat_global_bot_slug', '' );
            aichat_log_debug('[AIChat Core] maybe_render_global_widget: slug(opt)=' . ( $slug ?: 'EMPTY' ));
            if ( empty( $slug ) ) {
                $slug = $this->get_first_bot_slug();
                aichat_log_debug('[AIChat Core] maybe_render_global_widget: slug(fallback)=' . ( $slug ?: 'EMPTY' ));
                if ( empty( $slug ) ) {
                    aichat_log_debug('[AIChat Core] maybe_render_global_widget: abort no bots');
                    return;
                }
            }

            // Reutiliza el shortcode: incluye todos los data-* (type, avatar, closable/minimizable/draggable, etc.)
            echo do_shortcode( sprintf('[aichat id="%s" layout="floating"]', esc_attr($slug)) );
            aichat_log_debug('[AIChat Core] maybe_render_global_widget: rendered via shortcode bot=' . $slug );
        }
    }

    // Retrocompatibilidad: alias si alguien usa todavía AICHAT_Core
    if ( ! class_exists( 'AICHAT_Core' ) ) {
        class_alias( 'AIChat_Core', 'AICHAT_Core' );
    }
}
