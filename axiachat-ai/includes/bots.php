<?php
/**
 * Admin Page: Chatbots (tabs + panel; form se renderiza desde bots.js)
 */

if (! defined('ABSPATH')) {
	exit;
}

// Nota: los estilos/JS necesarios (bootstrap icons + bots.js) se encolan desde el hook en aichat.php.

function aichat_bots_settings_page()
{
	// Carga opciones de contextos (para el selector de embeddings)
	global $wpdb;
	$ctx_table = $wpdb->prefix . 'aichat_contexts';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Admin-only read of internal table.
	$contexts = $wpdb->get_results("SELECT id, name FROM $ctx_table ORDER BY id DESC", ARRAY_A);
	$embedding_options = array_map(function ($r) {
		return ['id' => (int) $r['id'], 'text' => $r['name']];
	}, $contexts);
	array_unshift($embedding_options, ['id' => 0, 'text' => '— None —']);

	// Prepara objeto JS (por si no has hecho wp_localize_script encolando bots.js)
	// Si ya fueron localizadas encolando el script (aichat.php) no sobreescribir; sólo fallback.
	$instruction_templates = function_exists('aichat_get_chatbot_templates') ? aichat_get_chatbot_templates() : [];
	if (function_exists('aichat_log_debug')) {
		aichat_log_debug('Bots page render templates', ['count' => is_array($instruction_templates) ? count($instruction_templates) : 0]);
	}
	$ajax_boot = [
	'ajax_url' => admin_url('admin-ajax.php'),
	'nonce' => wp_create_nonce('aichat_bots_nonce'),
	'embedding_options' => $embedding_options,
	'instruction_templates' => $instruction_templates,
	];
	?>
  <div class="wrap">
  <h1><?php esc_html_e('Chatbots', 'axiachat-ai'); ?></h1>

    <!-- Styles moved to assets/css/aichat-admin.css -->

    <div class="aichat-layout">
      <div class="aichat-bot-wrapper">
        <div class="aichat-tabs">
          <button type="button" id="aichat-tabs-prev" class="aichat-sbtn" aria-label="<?php esc_attr_e('Scroll left', 'axiachat-ai'); ?>" style="display:none">
            <i class="bi bi-chevron-left"></i>
          </button>

          <div id="aichat-tab-strip" class="aichat-tab-strip" role="tablist" aria-label="<?php esc_attr_e('Chatbot tabs', 'axiachat-ai'); ?>"></div>

          <button type="button" id="aichat-tabs-next" class="aichat-sbtn" aria-label="<?php esc_attr_e('Scroll right', 'axiachat-ai'); ?>" style="display:none">
            <i class="bi bi-chevron-right"></i>
          </button>

          <button type="button" id="aichat-add-bot" class="aichat-new-btn" aria-label="<?php esc_attr_e('Create new chatbot', 'axiachat-ai'); ?>">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>

        <div class="aichat-panel">
          <div class="aichat-panel-body" id="aichat-panel">
            <!-- bots.js inyecta el formulario aquí -->
          </div>
        </div>
      </div>

      <div class="aichat-preview-wrapper">
        <div class="aichat-preview-card">
          <div class="aichat-preview-head">
            Preview
          </div>
          <div class="aichat-preview-body">
            <iframe id="aichat-preview" title="AIChat Preview" loading="lazy"></iframe>
          </div>
        </div>
      </div>
    </div>

    <!-- Inline script removed: data now provided via wp_localize_script('aichat-bots-js','aichat_bots_ajax', ...) -->
  </div>
<?php }
