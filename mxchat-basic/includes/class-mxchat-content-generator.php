<?php
/**
 * MxChat Content Generator
 *
 * Handles AI-powered blog post and landing page generation
 * with image generation, SEO metadata, and inline editing.
 *
 * @package MxChat
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MxChat_Content_Generator {

    private $options;

    public function __construct() {
        $this->options = get_option('mxchat_options', array());

        // AJAX hooks (admin only, but wp_ajax_ prefix ensures that)
        add_action('wp_ajax_mxchat_generate_content', array($this, 'handle_generate_content'));
        add_action('wp_ajax_mxchat_content_edit', array($this, 'handle_content_edit'));
        add_action('wp_ajax_mxchat_content_progress', array($this, 'handle_content_progress'));
        add_action('wp_ajax_mxchat_save_content_setting', array($this, 'handle_save_content_setting'));
        add_action('wp_ajax_mxchat_content_history', array($this, 'handle_content_history'));
        add_action('wp_ajax_mxchat_load_post_for_edit', array($this, 'handle_load_post_for_edit'));
        add_action('wp_ajax_mxchat_delete_content', array($this, 'handle_delete_content'));
        add_action('wp_ajax_mxchat_update_post_status', array($this, 'handle_update_post_status'));

        // Background generation via loopback (nopriv because loopback doesn't carry cookies — auth via secret token)
        add_action('wp_ajax_nopriv_mxchat_generate_content_background', array($this, 'handle_generate_content_background'));
        add_action('wp_ajax_mxchat_generate_content_background', array($this, 'handle_generate_content_background'));

        // Frontend CSS injection — outputs generated styles in <head>
        add_action('wp_head', array($this, 'inject_generated_css'));

        // Hide admin bar inside content generator preview iframe (WordPress-native approach)
        add_filter('show_admin_bar', array($this, 'hide_admin_bar_in_preview'));

        // Disable wpautop and wptexturize for generated content.
        // wpautop inserts rogue <p> tags between block elements (section, div, etc.)
        // which breaks flex/grid layouts. wptexturize converts quotes inside CSS
        // values and data attributes into curly quotes, breaking functionality.
        add_filter('the_content', array($this, 'protect_generated_content'), 1);
        add_filter('the_content', array($this, 'restore_content_filters'), 999);
    }

    // ─── Content Filter Protection ──────────────────────────────────

    /**
     * Disable wpautop and wptexturize for MxChat-generated posts.
     *
     * WordPress applies these filters to the_content by default:
     * - wpautop: wraps text in <p> tags, breaking flex/grid layouts
     * - wptexturize: converts quotes to curly quotes, breaking attributes
     *
     * This runs at priority 1 (earliest) to remove the filters before
     * they execute, then restore_content_filters() at priority 999
     * re-adds them for subsequent posts (e.g. in archive/loop contexts).
     */
    public function protect_generated_content($content) {
        $post_id = get_the_ID();
        if (!$post_id) {
            return $content;
        }

        if (get_post_meta($post_id, '_mxchat_generated', true) !== '1') {
            return $content;
        }

        // Remove wpautop and wptexturize for this post
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'wptexturize');

        return $content;
    }

    /**
     * Re-add wpautop and wptexturize after our generated content has rendered.
     * This ensures other posts in the same page load (archives, widgets)
     * still get normal WordPress formatting.
     */
    public function restore_content_filters($content) {
        if (!has_filter('the_content', 'wpautop')) {
            add_filter('the_content', 'wpautop');
        }
        if (!has_filter('the_content', 'wptexturize')) {
            add_filter('the_content', 'wptexturize');
        }

        return $content;
    }

    // ─── Frontend CSS Injection ──────────────────────────────────────

    /**
     * Inject generated CSS into <head> on the frontend.
     * Fires on wp_head for any singular post/page that was created
     * by the content generator. CSS is stored in post meta to avoid
     * issues with wp_kses, wpautop, and wptexturize mangling styles
     * stored in post_content.
     */
    public function inject_generated_css() {
        if (!is_singular()) {
            return;
        }

        $post_id = get_the_ID();
        if (!$post_id) {
            return;
        }

        // Only inject on MxChat-generated posts
        if (get_post_meta($post_id, '_mxchat_generated', true) !== '1') {
            return;
        }

        $ai_css    = get_post_meta($post_id, '_mxchat_content_css', true);
        $fullwidth = get_post_meta($post_id, '_mxchat_fullwidth', true) === '1';
        $hide_title = get_post_meta($post_id, '_mxchat_hide_title', true) === '1';

        // Output the combined CSS
        echo $this->get_generated_css($fullwidth, $ai_css);

        // Hide the WordPress post/page title if configured.
        // Covers: generic WP, Astra, GeneratePress, Kadence, OceanWP, Neve,
        // Hello Elementor, Bricks, Divi, Blocksy, and common starter themes.
        if ($hide_title) {
            echo '<style>
.entry-title,
.page-title,
.post-title,
.wp-block-post-title,
/* Astra */
.ast-title-with-post-meta-wrapper,
.ast-the-title,
/* GeneratePress */
.generate-page-header .page-hero,
.entry-header .entry-title,
/* Kadence */
.entry-hero .entry-title,
.kadence-page-title,
.wp-site-blocks .entry-title,
/* OceanWP */
.ocean-single-post-header,
.page-header,
/* Neve */
.nv-page-title-wrap,
.nv-post-title,
/* Hello Elementor */
.elementor-page-title,
/* Divi */
.et_pb_title_container .entry-title,
/* Bricks */
.brxe-post-title,
/* Blocksy */
[data-hero] .page-title,
.hero-section .page-title,
/* Generic header containers */
.entry-header {
    display: none !important;
}
</style>' . "\n";
        }
    }

    /**
     * Hide the WordPress admin bar when the page is loaded inside the
     * content generator preview iframe (?mxchat_preview=1).
     *
     * Uses the native show_admin_bar filter so WordPress never renders
     * the bar at all — no CSS hacks, no flash of the bar disappearing.
     *
     * @param bool $show Whether to show the admin bar.
     * @return bool
     */
    public function hide_admin_bar_in_preview($show) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag, no data processing
        if (isset($_GET['mxchat_preview'])) {
            return false;
        }
        return $show;
    }

    // ─── HTML Sanitization ────────────────────────────────────────────

    /**
     * Sanitize AI-generated HTML allowing all safe CSS properties.
     * WordPress wp_kses_post() strips most inline styles (display, flex,
     * background, gradient, border-radius, box-shadow, etc.) which breaks
     * modern page layouts. This method uses a permissive allowlist for
     * admin-generated content only.
     */
    private function sanitize_generated_html($html) {
        $allowed = wp_kses_allowed_html('post');

        // Tags that need full style support
        $styled_tags = array(
            'div', 'section', 'article', 'header', 'footer', 'main', 'nav', 'aside',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'span', 'a', 'figure',
            'figcaption', 'img', 'ul', 'ol', 'li', 'blockquote', 'table', 'thead',
            'tbody', 'tr', 'th', 'td', 'button', 'strong', 'em', 'br', 'hr',
            'video', 'source', 'iframe', 'svg', 'path', 'circle', 'rect', 'line',
            'polyline', 'polygon', 'g', 'defs', 'use', 'symbol', 'text',
        );

        foreach ($styled_tags as $tag) {
            if (!isset($allowed[$tag])) {
                $allowed[$tag] = array();
            }
            $allowed[$tag]['style'] = true;
            $allowed[$tag]['class'] = true;
            $allowed[$tag]['id'] = true;
        }

        // iframe attributes for video embeds (YouTube, Vimeo, etc.)
        $allowed['iframe']['src'] = true;
        $allowed['iframe']['width'] = true;
        $allowed['iframe']['height'] = true;
        $allowed['iframe']['frameborder'] = true;
        $allowed['iframe']['allow'] = true;
        $allowed['iframe']['allowfullscreen'] = true;
        $allowed['iframe']['title'] = true;
        $allowed['iframe']['loading'] = true;

        // Ensure a/img have their needed attributes
        $allowed['a']['href'] = true;
        $allowed['a']['target'] = true;
        $allowed['a']['rel'] = true;
        $allowed['img']['src'] = true;
        $allowed['img']['alt'] = true;
        $allowed['img']['width'] = true;
        $allowed['img']['height'] = true;
        $allowed['img']['loading'] = true;

        // SVG attributes for inline icons
        foreach (array('svg', 'path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'g') as $svg_tag) {
            $allowed[$svg_tag]['xmlns'] = true;
            $allowed[$svg_tag]['viewbox'] = true;
            $allowed[$svg_tag]['fill'] = true;
            $allowed[$svg_tag]['stroke'] = true;
            $allowed[$svg_tag]['stroke-width'] = true;
            $allowed[$svg_tag]['stroke-linecap'] = true;
            $allowed[$svg_tag]['stroke-linejoin'] = true;
            $allowed[$svg_tag]['d'] = true;
            $allowed[$svg_tag]['cx'] = true;
            $allowed[$svg_tag]['cy'] = true;
            $allowed[$svg_tag]['r'] = true;
            $allowed[$svg_tag]['x'] = true;
            $allowed[$svg_tag]['y'] = true;
            $allowed[$svg_tag]['width'] = true;
            $allowed[$svg_tag]['height'] = true;
            $allowed[$svg_tag]['points'] = true;
            $allowed[$svg_tag]['x1'] = true;
            $allowed[$svg_tag]['y1'] = true;
            $allowed[$svg_tag]['x2'] = true;
            $allowed[$svg_tag]['y2'] = true;
            $allowed[$svg_tag]['transform'] = true;
        }

        // Allow all safe CSS properties via the safecss filter
        add_filter('safe_style_css', array($this, 'allow_all_safe_css'));
        $clean = wp_kses($html, $allowed);
        remove_filter('safe_style_css', array($this, 'allow_all_safe_css'));

        return $clean;
    }

    /**
     * Expand the list of allowed CSS properties for generated content.
     */
    public function allow_all_safe_css($styles) {
        $extra = array(
            'display', 'flex', 'flex-direction', 'flex-wrap', 'flex-grow', 'flex-shrink',
            'flex-basis', 'justify-content', 'align-items', 'align-self', 'gap', 'order',
            'grid', 'grid-template-columns', 'grid-template-rows', 'grid-column', 'grid-row',
            'grid-gap', 'grid-area',
            'position', 'top', 'right', 'bottom', 'left', 'z-index',
            'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
            'margin', 'margin-top', 'margin-right', 'margin-bottom', 'margin-left',
            'padding', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
            'background', 'background-color', 'background-image', 'background-size',
            'background-position', 'background-repeat', 'background-attachment',
            'border', 'border-top', 'border-right', 'border-bottom', 'border-left',
            'border-radius', 'border-color', 'border-style', 'border-width',
            'box-shadow', 'text-shadow',
            'color', 'font-size', 'font-weight', 'font-style', 'font-family',
            'line-height', 'letter-spacing', 'text-align', 'text-decoration', 'text-transform',
            'vertical-align', 'white-space', 'word-break', 'overflow', 'overflow-x', 'overflow-y',
            'opacity', 'visibility', 'cursor',
            'transition', 'transform', 'animation',
            'object-fit', 'object-position',
            'list-style', 'list-style-type',
            'aspect-ratio',
        );
        return array_unique(array_merge($styles, $extra));
    }

    // ─── Generation Pipeline ───────────────────────────────────────────

    /**
     * Main content generation handler
     */
    public function handle_generate_content() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $prompt        = sanitize_textarea_field($_POST['prompt'] ?? '');
        $content_type  = sanitize_text_field($_POST['content_type'] ?? 'post');
        $post_status   = sanitize_text_field($_POST['post_status'] ?? 'draft');
        $schedule_date = sanitize_text_field($_POST['schedule_date'] ?? '');
        $layout        = sanitize_text_field($_POST['layout'] ?? 'fullwidth');
        $title_display = sanitize_text_field($_POST['title_display'] ?? 'hide');

        if (empty($prompt)) {
            wp_send_json_error(array('message' => __('Please enter a prompt.', 'mxchat')));
        }

        // Validate inputs
        if (!in_array($content_type, array('post', 'page'), true)) {
            $content_type = 'post';
        }
        if (!in_array($post_status, array('draft', 'publish', 'future'), true)) {
            $post_status = 'draft';
        }

        // Generate a unique progress key and secret for the background worker
        $progress_key = 'mxchat_content_progress_' . get_current_user_id() . '_' . time();
        $secret = wp_generate_password(32, false);

        $this->update_progress($progress_key, 'starting', __('Starting generation...', 'mxchat'), 5);

        // Store generation parameters for the background worker
        set_transient($progress_key . '_params', array(
            'secret'        => $secret,
            'user_id'       => get_current_user_id(),
            'prompt'        => $prompt,
            'content_type'  => $content_type,
            'post_status'   => $post_status,
            'schedule_date' => $schedule_date,
            'layout'        => $layout,
            'title_display' => $title_display,
        ), 600); // 10 minute TTL

        // Spawn background generation via non-blocking loopback request.
        // This lets the current request end immediately so the browser gets
        // the progress_key back in <1 second, avoiding Cloudflare 524 timeouts.
        wp_remote_post(admin_url('admin-ajax.php'), array(
            'timeout'   => 0.01,
            'blocking'  => false,
            'sslverify' => apply_filters('https_local_ssl_verify', false),
            'body'      => array(
                'action'       => 'mxchat_generate_content_background',
                'progress_key' => $progress_key,
                'secret'       => $secret,
            ),
        ));

        // Return immediately — the browser starts polling for progress
        wp_send_json_success(array('progress_key' => $progress_key));
    }

    /**
     * Background content generation handler.
     * Called via non-blocking loopback from handle_generate_content().
     * Authenticated via secret token stored in transient (no nonce/cookie needed).
     */
    public function handle_generate_content_background() {
        $progress_key = sanitize_text_field($_POST['progress_key'] ?? '');
        $secret       = sanitize_text_field($_POST['secret'] ?? '');

        if (empty($progress_key) || empty($secret)) {
            die();
        }

        // Validate the secret token
        $params = get_transient($progress_key . '_params');
        if (!$params || !isset($params['secret']) || $params['secret'] !== $secret) {
            die();
        }

        // One-time use — delete params transient
        delete_transient($progress_key . '_params');

        // Set up execution environment
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }
        ignore_user_abort(true);

        // Restore the original user context
        wp_set_current_user($params['user_id']);

        $prompt        = $params['prompt'];
        $content_type  = $params['content_type'];
        $post_status   = $params['post_status'];
        $schedule_date = $params['schedule_date'];
        $layout        = $params['layout'];
        $title_display = $params['title_display'];

        $this->update_progress($progress_key, 'planning', __('Planning content structure...', 'mxchat'), 10);

        // Step 1: Plan the content
        $plan = $this->plan_content($prompt, $content_type);
        if (is_wp_error($plan)) {
            $this->update_progress($progress_key, 'error', $plan->get_error_message(), 0);
            die();
        }

        $this->update_progress($progress_key, 'images', __('Generating images...', 'mxchat'), 30);

        // Step 2: Generate images
        $image_urls = $this->generate_content_images($plan, $progress_key);

        $this->update_progress($progress_key, 'writing', __('Writing full content...', 'mxchat'), 60);

        // Step 3: Generate full HTML content
        $html_content = $this->generate_html_content($plan, $image_urls, $content_type, $prompt);
        if (is_wp_error($html_content)) {
            $this->update_progress($progress_key, 'error', $html_content->get_error_message(), 0);
            die();
        }

        $this->update_progress($progress_key, 'creating', __('Creating WordPress post...', 'mxchat'), 85);

        // Step 4: Create the WordPress post/page
        // Extract AI CSS before sanitization — stored in post meta, injected via wp_head
        $ai_css = $this->extract_css($html_content);
        $html_without_style = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html_content);
        $sanitized_html = $this->sanitize_generated_html($html_without_style);

        $post_args = array(
            'post_title'   => sanitize_text_field($plan['title']),
            'post_content' => $sanitized_html,
            'post_status'  => $post_status,
            'post_type'    => $content_type === 'page' ? 'page' : 'post',
            'meta_input'   => array(
                '_mxchat_generated'   => '1',
                '_mxchat_prompt'      => $prompt,
                '_mxchat_fullwidth'   => ($layout === 'fullwidth') ? '1' : '0',
                '_mxchat_hide_title'  => ($title_display === 'hide') ? '1' : '0',
                '_mxchat_content_css' => $ai_css,
            ),
        );

        // Handle scheduled posts
        if ($post_status === 'future' && !empty($schedule_date)) {
            $post_args['post_date']     = $schedule_date;
            $post_args['post_date_gmt'] = get_gmt_from_date($schedule_date);
        }

        $post_id = wp_insert_post($post_args, true);

        if (is_wp_error($post_id)) {
            $this->update_progress($progress_key, 'error', $post_id->get_error_message(), 0);
            die();
        }

        // Set featured image if we have one
        if (!empty($image_urls) && !empty($image_urls[0]['attachment_id'])) {
            set_post_thumbnail($post_id, $image_urls[0]['attachment_id']);
        }

        // Associate all generated images with this post and store IDs for reliable tracking
        $image_ids = array();
        foreach ($image_urls as $img) {
            if (!empty($img['attachment_id'])) {
                wp_update_post(array(
                    'ID'          => $img['attachment_id'],
                    'post_parent' => $post_id,
                ));
                $image_ids[] = $img['attachment_id'];
            }
        }
        if (!empty($image_ids)) {
            update_post_meta($post_id, '_mxchat_image_ids', $image_ids);
        }

        // Apply fullwidth/title settings via theme-specific post meta
        $this->apply_layout_settings($post_id, $layout, $title_display);

        // Step 5: Fill SEO metadata
        $this->fill_seo_metadata($post_id, $plan);

        // Build final result
        $preview_url = add_query_arg(array('preview' => 'true'), get_permalink($post_id));
        $edit_url    = admin_url('post.php?post=' . $post_id . '&action=edit');
        $permalink   = get_permalink($post_id);

        $images_for_response = array();
        foreach ($image_urls as $img) {
            if (!empty($img['url']) && !empty($img['attachment_id'])) {
                $thumb_url = wp_get_attachment_image_url($img['attachment_id'], 'medium');
                $images_for_response[] = array(
                    'url'           => $img['url'],
                    'thumbnail'     => $thumb_url ?: $img['url'],
                    'attachment_id' => $img['attachment_id'],
                );
            }
        }

        $result = array(
            'post_id'      => $post_id,
            'preview_url'  => $preview_url,
            'edit_url'     => $edit_url,
            'permalink'    => $permalink,
            'title'        => $plan['title'],
            'status'       => $post_status,
            'images'       => $images_for_response,
            'meta'         => array(
                'description' => $plan['meta_description'] ?? '',
                'keyword'     => !empty($plan['keywords']) ? $plan['keywords'][0] : '',
                'excerpt'     => '',
            ),
        );

        // Store the result in the progress transient so polling can retrieve it
        $this->update_progress($progress_key, 'done', __('Content generated successfully!', 'mxchat'), 100, $result);
        die();
    }

    /**
     * Handle content edit via mini-chat
     */
    public function handle_content_edit() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        // Extend PHP execution time for long AI calls
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $post_id          = intval($_POST['post_id'] ?? 0);
        $edit_instruction = sanitize_textarea_field($_POST['edit_instruction'] ?? '');

        if (!$post_id || empty($edit_instruction)) {
            wp_send_json_error(array('message' => __('Missing post ID or edit instruction.', 'mxchat')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'mxchat')));
        }

        $current_content = $post->post_content;
        $current_title   = $post->post_title;
        $current_css     = get_post_meta($post_id, '_mxchat_content_css', true);

        // Build the edit prompt — send both CSS and HTML so AI can edit either
        $system_prompt = "You are a content editor. You have an existing page that uses a CSS-first approach: a <style> block with mxg- prefixed classes followed by clean HTML.\n\nApply ONLY the requested change and return the complete updated output. Do not add commentary — return ONLY the updated <style> block + HTML.\n\nIMPORTANT RULES:\n- Keep the same overall structure\n- Only change what the user specifically asks for\n- Return the complete output (not just the changed part)\n- If the user asks to change the title, update the <h1> in the HTML\n- Maintain the <style> block — update CSS rules if the edit requires style changes\n- ALL class names must use the mxg- prefix\n- Do NOT add inline styles — all styling stays in the <style> block\n- Do NOT include HTML comments\n- Do NOT generate a <header>, <footer>, or <nav>";

        // Reconstruct the full content (CSS + HTML) for the AI to edit
        $full_content_for_ai = '';
        if (!empty($current_css)) {
            $full_content_for_ai = "<style>\n" . $current_css . "\n</style>\n";
        }
        $full_content_for_ai .= $current_content;

        $user_message = "Current post title: " . $current_title . "\n\nCurrent content (style block + HTML):\n" . $full_content_for_ai . "\n\nUser edit request: " . $edit_instruction;

        $messages = array(
            array('role' => 'user', 'content' => $user_message),
        );

        // Allow add-ons to handle the edit via tool calling (str_replace, etc.)
        // Filter returns null to fall through to the default full-rewrite approach.
        $result = apply_filters('mxchat_content_tool_edit', null, $full_content_for_ai, $edit_instruction, $current_title);

        if ($result === null) {
            // Default: AI rewrites entire page
            $result = $this->call_content_model($system_prompt, $messages, 16384);
        }

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Clean up code fences if present
        $result = trim($result);
        $result = preg_replace('/^```(?:html)?\s*/i', '', $result);
        $result = preg_replace('/\s*```\s*$/', '', $result);

        // Strip HTML comments
        $result = preg_replace('/<!--.*?-->/s', '', $result);

        // Extract title if it was changed (look for first h1)
        $new_title = $current_title;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $result, $title_match)) {
            $new_title = wp_strip_all_tags($title_match[1]);
        }

        // Extract CSS → meta, sanitize HTML → post_content
        $ai_css = $this->extract_css($result);
        $html_without_style = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $result);
        $sanitized_html = $this->sanitize_generated_html($html_without_style);

        // Clear invalid page template meta that causes "Invalid page template" errors
        $current_template = get_post_meta($post_id, '_wp_page_template', true);
        if (!empty($current_template) && $current_template !== 'default') {
            $theme_templates = wp_get_theme()->get_page_templates(get_post($post_id));
            if (!isset($theme_templates[$current_template])) {
                delete_post_meta($post_id, '_wp_page_template');
            }
        }

        // Update the post content (HTML only)
        $update_result = wp_update_post(array(
            'ID'           => $post_id,
            'post_title'   => sanitize_text_field($new_title),
            'post_content' => $sanitized_html,
        ), true);

        // Update CSS in meta (injected via wp_head)
        update_post_meta($post_id, '_mxchat_content_css', $ai_css);

        if (is_wp_error($update_result)) {
            wp_send_json_error(array('message' => $update_result->get_error_message()));
        }

        $preview_url = add_query_arg(array('preview' => 'true'), get_permalink($post_id));

        // Discover images — prefer stored IDs, fall back to post_parent query
        $images = $this->discover_post_images($post_id);

        wp_send_json_success(array(
            'post_id'     => $post_id,
            'preview_url' => $preview_url,
            'title'       => $new_title,
            'message'     => __('Content updated successfully.', 'mxchat'),
            'images'      => $images,
            'meta'        => array(
                'description' => get_post_meta($post_id, '_mxchat_meta_description', true),
                'keyword'     => $this->get_focus_keyword($post_id),
                'excerpt'     => get_post($post_id)->post_excerpt,
            ),
        ));
    }

    /**
     * Return current progress status for polling
     */
    public function handle_content_progress() {
        // Prevent proxy/CDN from caching progress responses
        nocache_headers();

        check_ajax_referer('mxchat_content_nonce', 'nonce');

        $progress_key = sanitize_text_field($_POST['progress_key'] ?? '');
        if (empty($progress_key)) {
            wp_send_json_error(array('message' => __('Invalid progress key.', 'mxchat')));
        }

        // Direct DB read — bypasses all caching layers for guaranteed freshness
        $progress = $this->get_progress($progress_key);
        if (!$progress) {
            wp_send_json_success(array(
                'step'    => 'waiting',
                'message' => __('Waiting...', 'mxchat'),
                'percent' => 0,
            ));
            return;
        }

        // Clean up completed/errored progress rows after reading
        if (in_array($progress['step'], array('done', 'error'), true)) {
            $this->delete_progress($progress_key);
        }

        wp_send_json_success($progress);
    }

    // ─── Content Planning ──────────────────────────────────────────────

    /**
     * Step 1: Ask AI to plan the content structure
     */
    private function plan_content($prompt, $content_type) {
        $type_label = ($content_type === 'page') ? 'landing page' : 'blog post';

        $system_prompt = "You are a professional content strategist. The user wants to create a {$type_label}. Analyze their request and create a detailed content plan.\n\nYou MUST respond with ONLY valid JSON (no markdown, no code fences, no commentary). Use this exact structure:\n\n{\"title\": \"SEO-optimized title\", \"slug\": \"url-friendly-slug\", \"meta_description\": \"155 character meta description for SEO\", \"keywords\": [\"keyword1\", \"keyword2\", \"keyword3\", \"keyword4\", \"keyword5\"], \"links\": [{\"label\": \"Button or link text\", \"url\": \"https://example.com/page\"}], \"sections\": [{\"type\": \"hero\", \"heading\": \"Main heading\", \"subheading\": \"Supporting text\", \"needs_image\": true, \"image_prompt\": \"Detailed prompt for hero image\"}, {\"type\": \"content\", \"heading\": \"Section heading\", \"key_points\": [\"point 1\", \"point 2\"], \"needs_image\": true, \"image_prompt\": \"Detailed prompt for section image\"}, {\"type\": \"content\", \"heading\": \"Another section\", \"key_points\": [\"point 1\", \"point 2\"], \"needs_image\": false, \"image_prompt\": \"\"}, {\"type\": \"cta\", \"heading\": \"Call to action heading\", \"subheading\": \"CTA supporting text\", \"needs_image\": false, \"image_prompt\": \"\"}]}\n\nGuidelines:\n- Create 5-8 sections for blog posts, 4-6 for landing pages\n- Include 2-4 sections that need images\n- Image prompts should be detailed, descriptive, and suitable for AI image generation\n- Image prompts should describe photorealistic or illustrative images relevant to the content\n- Keywords should be relevant long-tail SEO keywords\n- Title should be compelling and SEO-friendly\n- IMPORTANT: If the user specifies any URLs or links (for buttons, CTAs, navigation, etc.), you MUST capture every one of them in the \"links\" array with its label and exact URL. If no URLs are mentioned, use an empty array [].";

        $messages = array(
            array('role' => 'user', 'content' => "Create a {$type_label} about: {$prompt}"),
        );

        $result = $this->call_content_model($system_prompt, $messages);

        if (is_wp_error($result)) {
            return $result;
        }

        // Clean the response - remove markdown code fences if present
        $result = trim($result);
        $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
        $result = preg_replace('/\s*```\s*$/', '', $result);

        $plan = json_decode($result, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_parse_error', __('Failed to parse content plan. The AI response was not valid JSON.', 'mxchat'));
        }

        // Validate required fields
        if (empty($plan['title']) || empty($plan['sections'])) {
            return new WP_Error('invalid_plan', __('Content plan is missing required fields (title or sections).', 'mxchat'));
        }

        return $plan;
    }

    // ─── Image Generation ──────────────────────────────────────────────

    /**
     * Step 2: Generate images for sections that need them.
     * Uses cURL multi-handle to fire all image API requests in parallel,
     * then saves results to the media library sequentially.
     */
    private function generate_content_images($plan, $progress_key) {
        $options = get_option('mxchat_options', array());
        $enable_images = ($options['content_enable_images'] ?? 'on') === 'on';

        if (!$enable_images) {
            return array();
        }

        $image_sections = array();
        foreach ($plan['sections'] as $index => $section) {
            if (!empty($section['needs_image']) && !empty($section['image_prompt'])) {
                $image_sections[] = array(
                    'index'  => $index,
                    'prompt' => $section['image_prompt'],
                    'heading' => $section['heading'] ?? 'Section',
                );
            }
        }

        if (empty($image_sections)) {
            return array();
        }

        $total = count($image_sections);

        // Fallback to sequential if cURL multi is unavailable
        if (!function_exists('curl_multi_init')) {
            return $this->generate_content_images_sequential($image_sections, $total, $progress_key);
        }

        $this->update_progress(
            $progress_key,
            'images',
            sprintf(__('Generating %d images...', 'mxchat'), $total),
            30
        );

        // Build cURL requests for all images
        $image_model = $options['content_image_model'] ?? 'gpt-image-1.5';
        $curl_configs = array();

        foreach ($image_sections as $img) {
            $config = $this->build_image_request($img['prompt'], $image_model, $options);
            if (!is_wp_error($config)) {
                $curl_configs[] = array(
                    'section_index' => $img['index'],
                    'prompt'        => $img['prompt'],
                    'config'        => $config,
                );
            }
        }

        if (empty($curl_configs)) {
            return array();
        }

        // Fire all requests in parallel
        $multi = curl_multi_init();
        $handles = array();

        foreach ($curl_configs as $i => $item) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $item['config']['url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $item['config']['body']);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $item['config']['headers']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            curl_multi_add_handle($multi, $ch);
            $handles[$i] = array(
                'handle'        => $ch,
                'section_index' => $item['section_index'],
                'prompt'        => $item['prompt'],
            );
        }

        // Execute all requests concurrently
        $running = null;
        do {
            $status = curl_multi_exec($multi, $running);
            if ($running > 0) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        // Collect responses
        $raw_responses = array();
        foreach ($handles as $i => $h) {
            $body      = curl_multi_getcontent($h['handle']);
            $http_code = curl_getinfo($h['handle'], CURLINFO_HTTP_CODE);

            curl_multi_remove_handle($multi, $h['handle']);
            curl_close($h['handle']);

            if ($http_code === 200 && !empty($body)) {
                $raw_responses[] = array(
                    'section_index' => $h['section_index'],
                    'prompt'        => $h['prompt'],
                    'body'          => $body,
                );
            }
        }
        curl_multi_close($multi);

        $this->update_progress(
            $progress_key,
            'images',
            __('Saving images to media library...', 'mxchat'),
            50
        );

        // Process responses and save to media library (sequential — fast)
        $image_urls = array();
        foreach ($raw_responses as $resp) {
            $result = $this->process_image_response($resp['body'], $image_model, $options);
            if (!is_wp_error($result)) {
                // Store the original prompt for later regeneration by add-ons
                update_post_meta($result['attachment_id'], '_mxchat_image_prompt', $resp['prompt']);
                $image_urls[] = array(
                    'section_index' => $resp['section_index'],
                    'url'           => $result['url'],
                    'attachment_id' => $result['attachment_id'],
                );
            }
        }

        return $image_urls;
    }

    /**
     * Sequential fallback when cURL multi is not available.
     */
    private function generate_content_images_sequential($image_sections, $total, $progress_key) {
        $image_urls = array();

        foreach ($image_sections as $i => $img) {
            $step_num = $i + 1;
            $this->update_progress(
                $progress_key,
                'images',
                sprintf(__('Generating image %d of %d...', 'mxchat'), $step_num, $total),
                30 + (int)(($step_num / $total) * 25)
            );

            $image_result = $this->generate_single_image($img['prompt']);

            if (is_wp_error($image_result)) {
                continue;
            }

            // Store the original prompt for later regeneration by add-ons
            update_post_meta($image_result['attachment_id'], '_mxchat_image_prompt', $img['prompt']);

            $image_urls[] = array(
                'section_index' => $img['index'],
                'url'           => $image_result['url'],
                'attachment_id' => $image_result['attachment_id'],
            );
        }

        return $image_urls;
    }

    /**
     * Generate a single image using the configured image model
     */
    private function generate_single_image($prompt) {
        $options     = get_option('mxchat_options', array());
        $image_model = $options['content_image_model'] ?? 'gpt-image-1.5';

        if (strpos($image_model, 'gpt-image') === 0) {
            return $this->generate_openai_image($prompt, $options);
        } elseif (strpos($image_model, 'grok') === 0) {
            return $this->generate_xai_image($prompt, $options);
        } elseif (strpos($image_model, 'gemini') === 0) {
            return $this->generate_gemini_image($prompt, $image_model, $options);
        }

        return new WP_Error('unknown_model', __('Unknown image model configured.', 'mxchat'));
    }

    /**
     * Build a cURL-ready request config for a single image generation.
     * Used by the parallel image pipeline.
     *
     * @param string $prompt      Image generation prompt.
     * @param string $image_model The configured image model ID.
     * @param array  $options     Plugin options (contains API keys).
     * @return array|WP_Error     Array with 'url', 'headers', 'body' keys, or WP_Error.
     */
    private function build_image_request($prompt, $image_model, $options) {
        if (strpos($image_model, 'gpt-image') === 0) {
            $api_key = $options['api_key'] ?? '';
            if (empty($api_key)) {
                return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'mxchat'));
            }
            return array(
                'url'     => 'https://api.openai.com/v1/images/generations',
                'headers' => array(
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                ),
                'body' => wp_json_encode(array(
                    'model'         => 'gpt-image-1.5',
                    'prompt'        => $prompt,
                    'n'             => 1,
                    'size'          => '1536x1024',
                    'quality'       => 'medium',
                    'output_format' => 'png',
                )),
            );
        }

        if (strpos($image_model, 'grok') === 0) {
            $api_key = $options['xai_api_key'] ?? '';
            if (empty($api_key)) {
                return new WP_Error('no_api_key', __('xAI API key not configured.', 'mxchat'));
            }
            return array(
                'url'     => 'https://api.x.ai/v1/images/generations',
                'headers' => array(
                    'Authorization: Bearer ' . $api_key,
                    'Content-Type: application/json',
                ),
                'body' => wp_json_encode(array(
                    'model'           => $image_model,
                    'prompt'          => $prompt,
                    'n'               => 1,
                    'response_format' => 'url',
                )),
            );
        }

        if (strpos($image_model, 'gemini') === 0) {
            $api_key = $options['gemini_api_key'] ?? '';
            if (empty($api_key)) {
                return new WP_Error('no_api_key', __('Gemini API key not configured.', 'mxchat'));
            }
            $api_version = (strpos($image_model, 'preview') !== false) ? 'v1beta' : 'v1beta';
            return array(
                'url'     => "https://generativelanguage.googleapis.com/{$api_version}/models/{$image_model}:generateContent?key={$api_key}",
                'headers' => array(
                    'Content-Type: application/json',
                ),
                'body' => wp_json_encode(array(
                    'contents' => array(
                        array(
                            'parts' => array(
                                array('text' => $prompt),
                            ),
                        ),
                    ),
                    'generationConfig' => array(
                        'responseModalities' => array('TEXT', 'IMAGE'),
                        'imageConfig'        => array('aspectRatio' => '16:9'),
                    ),
                )),
            );
        }

        return new WP_Error('unknown_model', __('Unknown image model.', 'mxchat'));
    }

    /**
     * Process a raw API response body from an image generation call.
     * Decodes the response, extracts image data, and saves to media library.
     *
     * @param string $response_body Raw JSON response body.
     * @param string $image_model   The image model used (determines response format).
     * @param array  $options       Plugin options.
     * @return array|WP_Error       Array with 'url' and 'attachment_id', or WP_Error.
     */
    private function process_image_response($response_body, $image_model, $options = array()) {
        $decoded = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($decoded)) {
            return new WP_Error('json_error', __('Invalid image API response.', 'mxchat'));
        }

        // OpenAI GPT Image — b64_json or url
        if (strpos($image_model, 'gpt-image') === 0) {
            $b64_data = $decoded['data'][0]['b64_json'] ?? '';
            if (!empty($b64_data)) {
                return $this->save_image_to_media_library($b64_data, 'image/png');
            }
            $url = $decoded['data'][0]['url'] ?? '';
            if (!empty($url)) {
                return $this->save_image_url_to_media_library($url);
            }
            return new WP_Error('no_image_data', __('No image data in OpenAI response.', 'mxchat'));
        }

        // xAI Grok — url
        if (strpos($image_model, 'grok') === 0) {
            $url = $decoded['data'][0]['url'] ?? '';
            if (!empty($url)) {
                return $this->save_image_url_to_media_library($url);
            }
            return new WP_Error('no_image_data', __('No image data in xAI response.', 'mxchat'));
        }

        // Gemini — inlineData base64
        if (strpos($image_model, 'gemini') === 0) {
            if (isset($decoded['candidates'][0]['content']['parts'])) {
                foreach ($decoded['candidates'][0]['content']['parts'] as $part) {
                    $inline_data = $part['inlineData'] ?? $part['inline_data'] ?? null;
                    if ($inline_data && !empty($inline_data['data'])) {
                        $mime_type = $inline_data['mimeType'] ?? $inline_data['mime_type'] ?? 'image/png';
                        return $this->save_image_to_media_library($inline_data['data'], $mime_type);
                    }
                }
            }
            return new WP_Error('no_image_data', __('No image data in Gemini response.', 'mxchat'));
        }

        return new WP_Error('unknown_model', __('Unknown image model.', 'mxchat'));
    }

    /**
     * Generate image via OpenAI GPT Image 1.5
     */
    private function generate_openai_image($prompt, $options) {
        $api_key = $options['api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('OpenAI API key not configured.', 'mxchat'));
        }

        $body = array(
            'model'           => 'gpt-image-1.5',
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => '1536x1024',
            'quality'         => 'medium',
            'output_format'   => 'png',
        );

        $response = wp_remote_post('https://api.openai.com/v1/images/generations', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $response_body['error']['message'] ?? __('OpenAI image generation failed.', 'mxchat');
            return new WP_Error('openai_image_error', $error_msg);
        }

        // GPT Image returns b64_json by default
        $b64_data = $response_body['data'][0]['b64_json'] ?? '';
        if (!empty($b64_data)) {
            return $this->save_image_to_media_library($b64_data, 'image/png');
        }

        // Fallback: URL-based response
        $image_url = $response_body['data'][0]['url'] ?? '';
        if (!empty($image_url)) {
            return $this->save_image_url_to_media_library($image_url);
        }

        return new WP_Error('no_image_data', __('No image data in OpenAI response.', 'mxchat'));
    }

    /**
     * Generate image via xAI Grok Imagine
     */
    private function generate_xai_image($prompt, $options) {
        $api_key = $options['xai_api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('xAI API key not configured.', 'mxchat'));
        }

        $image_model = $options['content_image_model'] ?? 'grok-imagine-image';

        $body = array(
            'model'           => $image_model,
            'prompt'          => $prompt,
            'n'               => 1,
            'response_format' => 'url',
        );

        $response = wp_remote_post('https://api.x.ai/v1/images/generations', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code   = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $response_body['error']['message'] ?? __('xAI image generation failed.', 'mxchat');
            return new WP_Error('xai_image_error', $error_msg);
        }

        $image_url = $response_body['data'][0]['url'] ?? '';
        if (!empty($image_url)) {
            return $this->save_image_url_to_media_library($image_url);
        }

        return new WP_Error('no_image_data', __('No image data in xAI response.', 'mxchat'));
    }

    /**
     * Generate image via Gemini (Nano Banana)
     */
    private function generate_gemini_image($prompt, $model, $options) {
        $api_key = $options['gemini_api_key'] ?? '';
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured.', 'mxchat'));
        }

        $body = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt),
                    ),
                ),
            ),
            'generationConfig' => array(
                'responseModalities' => array('TEXT', 'IMAGE'),
                'imageConfig' => array(
                    'aspectRatio' => '16:9',
                ),
            ),
        );

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code   = wp_remote_retrieve_response_code($response);
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $response_body['error']['message'] ?? __('Gemini image generation failed.', 'mxchat');
            return new WP_Error('gemini_image_error', $error_msg);
        }

        // Extract image from Gemini response
        if (isset($response_body['candidates'][0]['content']['parts'])) {
            foreach ($response_body['candidates'][0]['content']['parts'] as $part) {
                $inline_data = $part['inlineData'] ?? $part['inline_data'] ?? null;
                if ($inline_data && !empty($inline_data['data'])) {
                    $mime_type = $inline_data['mimeType'] ?? $inline_data['mime_type'] ?? 'image/png';
                    return $this->save_image_to_media_library($inline_data['data'], $mime_type);
                }
            }
        }

        return new WP_Error('no_image_data', __('No image data in Gemini response.', 'mxchat'));
    }

    // ─── Media Library Helpers ──────────────────────────────────────────

    /**
     * Save base64 image data to WordPress media library
     */
    private function save_image_to_media_library($base64_data, $mime_type = 'image/png') {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $image_content = base64_decode($base64_data);
        if ($image_content === false) {
            return new WP_Error('decode_failed', __('Failed to decode image data.', 'mxchat'));
        }

        $extension = 'png';
        if (strpos($mime_type, 'jpeg') !== false || strpos($mime_type, 'jpg') !== false) {
            $extension = 'jpg';
        } elseif (strpos($mime_type, 'webp') !== false) {
            $extension = 'webp';
        }

        $filename  = 'mxchat-content-' . time() . '-' . wp_generate_password(6, false) . '.' . $extension;
        $temp_file = wp_tempnam($filename);

        if (!$temp_file) {
            return new WP_Error('temp_file_failed', __('Could not create temporary file.', 'mxchat'));
        }

        $bytes = file_put_contents($temp_file, $image_content);
        if ($bytes === false) {
            @unlink($temp_file);
            return new WP_Error('write_failed', __('Could not write image file.', 'mxchat'));
        }

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $temp_file,
            'type'     => $mime_type,
        );

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }

        return array(
            'url'           => wp_get_attachment_url($attachment_id),
            'attachment_id' => $attachment_id,
        );
    }

    /**
     * Download an image from URL and save to WordPress media library
     */
    private function save_image_url_to_media_library($image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($image_url, 120);
        if (is_wp_error($tmp)) {
            return $tmp;
        }

        $filename = 'mxchat-content-' . time() . '-' . wp_generate_password(6, false) . '.jpg';

        $file_array = array(
            'name'     => $filename,
            'tmp_name' => $tmp,
        );

        $attachment_id = media_handle_sideload($file_array, 0);

        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return $attachment_id;
        }

        return array(
            'url'           => wp_get_attachment_url($attachment_id),
            'attachment_id' => $attachment_id,
        );
    }

    // ─── HTML Content Generation ────────────────────────────────────────

    /**
     * Step 3: Generate the full HTML content
     */
    private function generate_html_content($plan, $image_urls, $content_type, $original_prompt = '') {
        $type_label = ($content_type === 'page') ? 'landing page' : 'blog post';
        $has_images = !empty($image_urls);

        // Build image URL map by section index
        $image_map = array();
        foreach ($image_urls as $img) {
            $image_map[$img['section_index']] = $img['url'];
        }

        // Include image URLs in the plan for the AI
        $plan_with_images = $plan;
        foreach ($plan_with_images['sections'] as $index => &$section) {
            if (isset($image_map[$index])) {
                $section['image_url'] = $image_map[$index];
            }
        }
        unset($section);

        // Strip image fields from plan when no images, so the AI doesn't
        // render image prompts as visible placeholder text
        if (!$has_images) {
            foreach ($plan_with_images['sections'] as &$section) {
                unset($section['needs_image'], $section['image_prompt']);
            }
            unset($section);
        }

        $plan_json = wp_json_encode($plan_with_images, JSON_PRETTY_PRINT);

        // Build explicit image URL reference so the AI can't miss them
        $image_reference = '';
        if ($has_images && !empty($image_map)) {
            $image_reference = "\n\n=== IMAGE URL REFERENCE (use these EXACT URLs) ===\n";
            foreach ($image_map as $section_idx => $url) {
                $section_heading = $plan['sections'][$section_idx]['heading'] ?? "Section {$section_idx}";
                $image_reference .= "Section \"{$section_heading}\": {$url}\n";
            }
            $image_reference .= "=== END IMAGE URLS — Do NOT use any other image URLs ===\n";
        }

        if ($content_type === 'page') {
            $system_prompt = $this->get_landing_page_prompt($has_images);
        } else {
            $system_prompt = $this->get_blog_post_prompt($has_images);
        }

        // Allow add-ons to modify the system prompt (e.g. internal linking instructions)
        $system_prompt = apply_filters('mxchat_content_system_prompt', $system_prompt, $plan, $content_type);

        // Include the original user prompt so the AI can see any specific URLs, links, or details the user mentioned
        $original_context = '';
        if (!empty($original_prompt)) {
            $original_context = "\n\n=== ORIGINAL USER REQUEST ===\n{$original_prompt}\n=== END ORIGINAL REQUEST ===\nIMPORTANT: If the user specified any URLs or links above, you MUST use those exact URLs in the corresponding buttons/links in the HTML. Do NOT replace user-specified URLs with href=\"#\".\n";
        }

        $user_message = "Generate the full HTML content for this {$type_label}. Here is the content plan:\n\n{$plan_json}{$image_reference}{$original_context}";

        // Allow add-ons to append data to the user message (e.g. internal links list)
        $user_message = apply_filters('mxchat_content_user_message', $user_message, $plan, $content_type);

        $messages = array(
            array('role' => 'user', 'content' => $user_message),
        );

        $result = $this->call_content_model($system_prompt, $messages, 16384);

        if (is_wp_error($result)) {
            return $result;
        }

        // Clean up - remove markdown code fences if present
        $result = trim($result);
        $result = preg_replace('/^```(?:html)?\s*/i', '', $result);
        $result = preg_replace('/\s*```\s*$/', '', $result);

        // Strip HTML comments — WordPress wpautop() wraps them in <p> tags
        // causing visible white blocks like <p><!-- PRICING SECTION --></p>
        $result = preg_replace('/<!--.*?-->/s', '', $result);

        // Post-process: replace any hallucinated image URLs with our real ones
        if ($has_images) {
            $result = $this->replace_hallucinated_images($result, $image_urls);
        }

        // Allow add-ons to post-process generated HTML
        $result = apply_filters('mxchat_content_generated_html', $result, $plan, $content_type);

        return $result;
    }

    /**
     * Replace any image URL that isn't one of our provided real URLs.
     * AI models hallucinate fake URLs from random domains — instead of
     * blocklisting domains, we allowlist only the URLs we actually provided.
     */
    private function replace_hallucinated_images($html, $image_urls) {
        if (empty($image_urls)) {
            return $html;
        }

        // Build set of our real URLs
        $real_urls = array();
        foreach ($image_urls as $img) {
            if (!empty($img['url'])) {
                $real_urls[] = $img['url'];
            }
        }

        if (empty($real_urls)) {
            return $html;
        }

        $index = 0;

        $html = preg_replace_callback('/<img([^>]+)src=["\']([^"\']+)["\']([^>]*)>/i', function($matches) use ($real_urls, &$index) {
            $src = $matches[2];

            // If this src is one of our real URLs, keep it
            if (in_array($src, $real_urls, true)) {
                return $matches[0];
            }

            // Otherwise replace with the next real URL
            $replacement_url = $real_urls[$index % count($real_urls)];
            $index++;
            return '<img' . $matches[1] . 'src="' . esc_url($replacement_url) . '"' . $matches[3] . '>';
        }, $html);

        return $html;
    }

    /**
     * System prompt optimized for landing page generation (CSS-first approach).
     *
     * The AI outputs a <style> block with ALL CSS using mxg- prefixed classes,
     * followed by clean semantic HTML referencing those classes. No inline styles.
     */
    private function get_landing_page_prompt($has_images = false) {
        $image_rules = $has_images
            ? 'IMAGES:
- You will receive image URLs in the content plan JSON under "image_url" for each section
- You MUST use ONLY those exact image URLs — copy them character for character into your <img> tags
- NEVER invent, guess, or fabricate ANY image URLs
- If a section has no image_url, do NOT add an image for that section
- Format: <img class="mxg-img" src="EXACT_URL_FROM_PLAN" alt="descriptive alt text">'
            : 'IMAGES:
- Do NOT include any <img> tags at all
- Do NOT add any images, placeholders, or image URLs
- Design all sections using only text, colors, gradients, and layout';

        return <<<PROMPT
You are an expert web designer and copywriter. Generate a visually stunning, modern landing page.

YOUR OUTPUT FORMAT — you MUST follow this exactly:
1. First, output a single <style> block containing ALL CSS for the page
2. Then, output clean semantic HTML that references those CSS classes
3. Return ONLY the <style> block followed by the HTML — no markdown, no code fences, no commentary

CSS RULES:
- ALL class names MUST start with "mxg-" prefix (e.g. mxg-hero, mxg-card, mxg-btn-primary)
- Do NOT use any inline styles on HTML elements — put ALL styling in the <style> block
- Do NOT set font-family on anything (inherit from the WordPress theme)
- Include responsive @media queries inside your <style> block:
  - @media (max-width: 768px) — tablet breakpoint (stack columns, reduce font sizes, adjust padding)
  - @media (max-width: 480px) — mobile breakpoint (further reduce sizes)
- Use these required class names (we add responsive overrides for them):
  - mxg-container — inner content wrapper (max-width: 1200px; margin: 0 auto)
  - mxg-row — flex row for side-by-side layouts
  - mxg-col — each column in an mxg-row
  - mxg-grid — flex-wrap grid for cards
  - mxg-card — each card in an mxg-grid
  - mxg-hero-heading — the main h1 in the hero section
  - mxg-section-heading — h2 section headings
- You may create additional mxg- classes as needed (e.g. mxg-hero, mxg-cta-section, mxg-btn-primary, mxg-subtitle, mxg-features)

DESIGN SYSTEM:
- Wrap everything in <div class="mxg-wrapper">
- Each <section> should be full-width with its own background color/gradient
- Inside each section: <div class="mxg-container">
- For side-by-side layouts: <div class="mxg-row"> with <div class="mxg-col"> children
- For card grids: <div class="mxg-grid"> with <div class="mxg-card"> children

HERO SECTION:
- Full-width dark or gradient background
- Two-column layout (mxg-row): text column + visual/feature column
- Large h1 with class="mxg-hero-heading" (3rem+ on desktop)
- Subheading paragraph
- CTA buttons (mxg-btn-primary, mxg-btn-secondary)
- Generous vertical padding (80px+)

CONTENT SECTIONS:
- Alternate light (#ffffff) and subtle gray (#f8fafc) backgrounds
- Two-column layouts alternating content left/right
- Card grids for features/benefits
- Each section gets its own mxg- class for unique styling

TYPOGRAPHY:
- Hero heading: 3rem desktop, scales down in your media queries
- Section headings: 2.25rem desktop
- Body text: 1.1rem, line-height 1.7
- Light text on dark backgrounds (#e2e8f0), dark text on light backgrounds (#334155)

CTA SECTION (final):
- Bold background color or gradient
- Centered text with large heading
- Prominent CTA button

{$image_rules}

CRITICAL RULES:
- ALL styling goes in the <style> block — ZERO inline styles
- ALL class names use the mxg- prefix — no unprefixed classes
- The <style> block MUST include @media responsive queries
- No shortcodes, no WordPress-specific markup, no page builder code
- Use semantic HTML: section, h1-h3, p, a, div, img, ul, li, strong, em
- Make the copy compelling, specific, and conversion-focused
- If the user provided specific URLs/links in their request or in the plan's "links" array, you MUST use those exact URLs in the corresponding buttons and anchor tags
- Only use href="#" as a fallback for links where no URL was specified by the user
- Do NOT generate a <header>, <footer>, or <nav> — this goes INSIDE an existing WordPress page
- Do NOT include HTML comments
PROMPT;
    }

    /**
     * System prompt optimized for blog post generation (CSS-first approach).
     *
     * The AI outputs a <style> block with ALL CSS using mxg- prefixed classes,
     * followed by clean semantic HTML referencing those classes. No inline styles.
     */
    private function get_blog_post_prompt($has_images = false) {
        $image_rules = $has_images
            ? 'IMAGES:
- You will receive image URLs in the content plan JSON under "image_url" for each section
- You MUST use ONLY those exact image URLs — copy them character for character into your <img> tags
- NEVER invent, guess, or fabricate ANY image URLs
- If a section has no image_url, do NOT add an image for that section
- Format: <img class="mxg-img" src="EXACT_URL_FROM_PLAN" alt="descriptive alt text">
- Place naturally within the content flow between sections'
            : 'IMAGES:
- Do NOT include any <img> tags at all
- Do NOT add any images, placeholders, or image URLs
- Rely on text formatting, blockquotes, and takeaway boxes for visual interest';

        return <<<PROMPT
You are an expert content writer and web designer. Generate a beautifully formatted, long-form blog post.

YOUR OUTPUT FORMAT — you MUST follow this exactly:
1. First, output a single <style> block containing ALL CSS for the post
2. Then, output clean semantic HTML that references those CSS classes
3. Return ONLY the <style> block followed by the HTML — no markdown, no code fences, no commentary

CSS RULES:
- ALL class names MUST start with "mxg-" prefix (e.g. mxg-article, mxg-meta, mxg-blockquote, mxg-takeaway)
- Do NOT use any inline styles on HTML elements — put ALL styling in the <style> block
- Do NOT set font-family on anything (inherit from the WordPress theme)
- Include responsive @media queries inside your <style> block:
  - @media (max-width: 768px) — tablet breakpoint
  - @media (max-width: 480px) — mobile breakpoint
- Use these required class names (we add responsive overrides for them):
  - mxg-container — article wrapper (max-width: 800px; margin: 0 auto)
  - mxg-hero-heading — the main h1
  - mxg-section-heading — h2 section headings
- You may create additional mxg- classes as needed (e.g. mxg-meta, mxg-blockquote, mxg-takeaway, mxg-highlight, mxg-img)

LAYOUT:
- Wrap in <article class="mxg-container">
- Clean, readable blog layout — single column, generous whitespace
- max-width: 800px, centered, with comfortable padding

HEADER:
- <h1 class="mxg-hero-heading"> — large, bold title (2.5rem desktop)
- Meta line below: <p class="mxg-meta"> — publish date, estimated read time, subtle color

BODY CONTENT:
- Write 1500-3000 words of genuinely useful, well-researched content
- <h2 class="mxg-section-heading"> for main sections (1.75rem desktop)
- H3 subheadings with their own mxg- class
- Well-spaced paragraphs (1.1rem, line-height 1.8)
- Varied structures: paragraphs, bullet lists, numbered lists, blockquotes, key takeaway boxes

SPECIAL ELEMENTS:
- Blockquotes: <blockquote class="mxg-blockquote"> with left accent border, subtle background
- Key takeaway boxes: <div class="mxg-takeaway"> with gradient background, border, rounded corners, bold heading inside
- Highlighted stats: <span class="mxg-highlight"> with accent color and bold weight

{$image_rules}

CONCLUSION:
- Clear summary section with H2 heading
- Wrap up key points
- End with a subtle CTA or next-steps suggestion

CRITICAL RULES:
- ALL styling goes in the <style> block — ZERO inline styles
- ALL class names use the mxg- prefix — no unprefixed classes
- The <style> block MUST include @media responsive queries
- No shortcodes, no WordPress-specific markup
- Write substantive, expert-level content — not generic filler
- Use semantic HTML: article, h1-h3, p, ul, ol, li, blockquote, img, strong, em, a, div, span
- If the user provided specific URLs/links in their request or in the plan's "links" array, you MUST use those exact URLs in the corresponding anchor tags
- Only use href="#" as a fallback for links where no URL was specified by the user (internal links to real posts will be provided separately if available)
- Do NOT generate a <header>, <footer>, or <nav> — this goes INSIDE an existing WordPress page
- Do NOT include HTML comments
PROMPT;
    }

    // ─── CSS Extraction ──────────────────────────────────────────────

    /**
     * Extract CSS content from <style> tags in AI-generated HTML.
     * WordPress wp_kses strips <style> tags during sanitization,
     * so we pull the CSS out first and re-inject it after sanitizing.
     *
     * @param string $html Raw AI-generated HTML that may contain <style> blocks.
     * @return string The extracted CSS rules (without <style> tags), or empty string.
     */
    private function extract_css($html) {
        $css = '';
        if (preg_match_all('/<style[^>]*>(.*?)<\/style>/is', $html, $matches)) {
            foreach ($matches[1] as $block) {
                $css .= trim($block) . "\n";
            }
        }
        return trim($css);
    }

    // ─── Layout / Fullwidth Settings ──────────────────────────────────

    /**
     * Build a single <style> block for generated content.
     *
     * Merges three layers:
     * 1. Theme padding reset (when fullwidth is enabled)
     * 2. Our mxg- responsive overrides (always)
     * 3. AI-generated CSS extracted from the content (page-specific design)
     *
     * @param bool   $fullwidth Whether to include theme padding reset rules.
     * @param string $ai_css    Raw CSS extracted from the AI output (no <style> tags).
     * @return string Complete <style>...</style> block ready to prepend to post content.
     */
    private function get_generated_css($fullwidth = true, $ai_css = '') {
        $css = '<style>' . "\n";

        // Layer 1: Theme & page builder padding reset — only when fullwidth is selected
        if ($fullwidth) {
            $css .= '/* MxChat — Fullwidth Theme Reset */
/* Generic WordPress themes */
.entry-content-wrap,
.entry-content,
.post-inner .entry-content,
.container.site-content,
article .entry-content,
.content-area .site-main,
.single-content .entry-content,
.type-post .entry-content,
.type-page .entry-content,
.page .entry-content,
.single .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Astra */
.ast-container .entry-content,
.site-content .ast-container,
.ast-separate-container .ast-article-single,
.ast-separate-container .ast-article-post,
.ast-separate-container .ast-article-page {
    padding: 0 !important;
    margin: 0 auto !important;
    max-width: 100% !important;
    width: 100% !important;
    background: transparent !important;
}
.ast-separate-container .entry-content {
    margin: 0 !important;
}
/* GeneratePress */
.inside-article .entry-content,
.generate-columns-container,
.inside-article {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Kadence */
.kb-row-layout-wrap,
.entry-content-wrap,
.content-container.site-container {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
.content-style-unboxed .entry:not(.loop-entry),
.content-style-boxed .entry:not(.loop-entry) {
    box-shadow: none !important;
    border-radius: 0 !important;
    margin: 0 !important;
    padding: 0 !important;
}
/* OceanWP */
.ocean-content .entry,
#content-wrap .container {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Neve */
.nv-single-post-wrap .entry-content,
.nv-content-wrap .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Hello Elementor / Elementor default theme */
.site-main .elementor-section-wrap,
.elementor-page .page-content .entry-content,
.elementor-default .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Bricks Builder */
.brxe-post-content .entry-content,
.bricks-layout-wrapper .entry-content,
.brxe-container .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Divi */
.et_pb_post .entry-content,
#main-content .container .entry-content,
.et_full_width_page .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Beaver Builder */
.fl-post-content .entry-content,
.fl-content-full .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Blocksy */
.entry-content[data-source],
.site-main > article > .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
/* Spectra / starter templates */
.uagb-body-wrapper .entry-content,
.starter-template-content .entry-content {
    padding-left: 0 !important;
    padding-right: 0 !important;
    max-width: 100% !important;
    width: 100% !important;
}
';
        }

        // Layer 2: CSS isolation + responsive overrides — always included.
        // Page builders (Elementor, Bricks, Divi, Beaver) inject global CSS
        // that can override display, margin, padding, and box-sizing on generic
        // elements. The mxg-wrapper scope ensures our layout rules take priority.
        $css .= '/* MxChat — CSS Isolation & Responsive Overrides */
.mxg-wrapper { box-sizing: border-box; }
.mxg-wrapper *, .mxg-wrapper *::before, .mxg-wrapper *::after { box-sizing: inherit; }
.mxg-wrapper img { max-width: 100%; height: auto; }
.mxg-wrapper section { clear: both; }
.mxg-row { display: flex; flex-wrap: wrap; }
.mxg-col { min-width: 0; }
.mxg-grid { display: flex; flex-wrap: wrap; }
.mxg-container { box-sizing: border-box; width: 100%; }

@media (max-width: 768px) {
    .mxg-row { flex-direction: column !important; gap: 24px !important; }
    .mxg-col { flex: 1 1 100% !important; width: 100% !important; max-width: 100% !important; }
    .mxg-hero-heading { font-size: 2.2rem !important; }
    .mxg-section-heading { font-size: 1.6rem !important; }
    .mxg-container { padding-left: 20px !important; padding-right: 20px !important; }
    .mxg-card { flex: 1 1 100% !important; }
    .mxg-grid { gap: 16px !important; }
}
@media (max-width: 480px) {
    .mxg-hero-heading { font-size: 1.75rem !important; }
    .mxg-section-heading { font-size: 1.35rem !important; }
    .mxg-container { padding-left: 16px !important; padding-right: 16px !important; }
}
';

        // Layer 3: AI-generated CSS (page-specific design)
        if (!empty($ai_css)) {
            $css .= "\n/* MxChat — AI Generated Styles */\n";
            $css .= $ai_css . "\n";
        }

        $css .= '</style>';
        return $css;
    }

    /**
     * Apply layout settings via theme-specific and builder-specific post meta.
     *
     * Supports: Astra, GeneratePress, Kadence, OceanWP, Neve, Blocksy,
     * Elementor, Bricks Builder, Divi, Beaver Builder, and generic WordPress.
     *
     * Page builders that are installed but NOT used to edit this post will
     * still respect standard WordPress post_content — this method sets the
     * right meta so the theme renders it fullwidth without sidebar.
     */
    private function apply_layout_settings($post_id, $layout, $title_display) {
        $is_fullwidth  = ($layout === 'fullwidth');
        $hide_title    = ($title_display === 'hide');

        // ── Astra Theme ──
        if (defined('ASTRA_THEME_VERSION') || get_template() === 'astra') {
            if ($is_fullwidth) {
                update_post_meta($post_id, 'site-content-layout', 'page-builder');
                update_post_meta($post_id, 'site-sidebar-layout', 'no-sidebar');
            }
            if ($hide_title) {
                update_post_meta($post_id, 'site-post-title', 'disabled');
            }
        }

        // ── GeneratePress Theme ──
        if (defined('GENERATE_VERSION') || get_template() === 'generatepress') {
            if ($is_fullwidth) {
                update_post_meta($post_id, '_generate-sidebar-layout-meta', 'no-sidebar');
                update_post_meta($post_id, '_generate-full-width-content', 'true');
            }
            if ($hide_title) {
                update_post_meta($post_id, '_generate-disable-title', 'true');
            }
        }

        // ── Kadence Theme ──
        if (class_exists('Kadence\\Theme') || get_template() === 'kadence') {
            if ($is_fullwidth) {
                update_post_meta($post_id, '_kad_post_layout', 'fullwidth');
                update_post_meta($post_id, '_kad_post_content_style', 'unboxed');
            }
            if ($hide_title) {
                update_post_meta($post_id, '_kad_post_title', 'hide');
            }
        }

        // ── OceanWP Theme ──
        if (class_exists('Ocean_Extra') || get_template() === 'oceanwp') {
            if ($is_fullwidth) {
                update_post_meta($post_id, 'oceanwp_post_layout', 'full-width');
                update_post_meta($post_id, 'ocean_content_layout', 'full-width');
            }
            if ($hide_title) {
                update_post_meta($post_id, 'oceanwp_disable_title', 'on');
            }
        }

        // ── Neve Theme ──
        if (get_template() === 'neve') {
            if ($is_fullwidth) {
                update_post_meta($post_id, 'neve_meta_sidebar', 'full-width');
                update_post_meta($post_id, 'neve_meta_container', 'full-width');
            }
            if ($hide_title) {
                update_post_meta($post_id, 'neve_meta_disable_title', 'on');
            }
        }

        // ── Blocksy Theme ──
        if (get_template() === 'blocksy') {
            if ($is_fullwidth) {
                update_post_meta($post_id, 'page_structure_type', 'type-4');
            }
            if ($hide_title) {
                update_post_meta($post_id, 'disable_header', 'yes');
            }
        }

        // ── Elementor Canvas/Full Width ──
        // When Elementor is installed, use its Canvas template for the cleanest
        // fullwidth output (no header/footer/sidebar chrome from the theme).
        // The post still uses standard post_content — Elementor only takes over
        // rendering when _elementor_edit_mode is set (which we don't set).
        if (defined('ELEMENTOR_VERSION') && $is_fullwidth) {
            $post_type = get_post_type($post_id);
            $templates = wp_get_theme()->get_page_templates(get_post($post_id), $post_type);

            // Prefer Elementor Canvas (no theme chrome at all)
            if (isset($templates['elementor_canvas'])) {
                update_post_meta($post_id, '_wp_page_template', 'elementor_canvas');
            } elseif (isset($templates['elementor_header_footer'])) {
                update_post_meta($post_id, '_wp_page_template', 'elementor_header_footer');
            }
        }

        // ── Divi Theme / Divi Builder ──
        if (defined('ET_BUILDER_VERSION') || get_template() === 'Divi') {
            if ($is_fullwidth) {
                update_post_meta($post_id, '_et_pb_page_layout', 'et_full_width_page');
                update_post_meta($post_id, '_et_pb_side_nav', 'off');
            }
            if ($hide_title) {
                update_post_meta($post_id, '_et_pb_show_title', 'off');
            }
        }

        // ── Beaver Builder ──
        if (class_exists('FLBuilder') || class_exists('FLBuilderLoader')) {
            if ($is_fullwidth) {
                // Beaver Themer uses this meta for sidebar control
                update_post_meta($post_id, '_fl_builder_sidebar', 'no_sidebar');
            }
        }

        // ── Bricks Builder ──
        // Bricks uses its own rendering when _bricks_editor_mode is set.
        // For standard WP content, it falls through to the theme's template.
        // No special meta needed — our CSS resets and wp_head injection handle it.

        // ── Generic full-width page template ──
        // Only set _wp_page_template if the theme actually has a matching template file
        // AND we haven't already set one above (e.g. Elementor Canvas).
        // Setting a non-existent template causes "Invalid page template" errors on wp_update_post().
        // Our CSS injection via wp_head already handles fullwidth layout for all themes.
        if ($is_fullwidth) {
            $current_template = get_post_meta($post_id, '_wp_page_template', true);
            if (empty($current_template) || $current_template === 'default') {
                $theme_templates = wp_get_theme()->get_page_templates(get_post($post_id));
                foreach ($theme_templates as $file => $label) {
                    if (stripos($file, 'full') !== false && stripos($file, 'width') !== false) {
                        update_post_meta($post_id, '_wp_page_template', $file);
                        break;
                    }
                }
            }
        }
    }

    // ─── SEO Metadata ──────────────────────────────────────────────────

    /**
     * Step 5: Fill SEO metadata for the generated post
     */
    private function fill_seo_metadata($post_id, $plan) {
        $meta_description = $plan['meta_description'] ?? '';
        $keywords         = $plan['keywords'] ?? array();
        $focus_keyword    = !empty($keywords) ? $keywords[0] : '';
        $keywords_string  = implode(', ', $keywords);

        // Always store in custom meta as fallback
        update_post_meta($post_id, '_mxchat_meta_description', sanitize_text_field($meta_description));
        update_post_meta($post_id, '_mxchat_keywords', sanitize_text_field($keywords_string));

        // Yoast SEO
        if (defined('WPSEO_VERSION')) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_text_field($meta_description));
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($focus_keyword));
        }

        // RankMath
        if (class_exists('RankMath')) {
            update_post_meta($post_id, 'rank_math_description', sanitize_text_field($meta_description));
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($focus_keyword));
        }

        // All in One SEO
        if (function_exists('aioseo')) {
            update_post_meta($post_id, '_aioseo_description', sanitize_text_field($meta_description));
            update_post_meta($post_id, '_aioseo_keywords', sanitize_text_field($keywords_string));
        }
    }

    // ─── AI Model Caller ────────────────────────────────────────────────

    /**
     * Call the configured content model
     */
    private function call_content_model($system_prompt, $messages, $max_tokens = 4096) {
        $options = get_option('mxchat_options', array());
        $model   = $options['content_model'] ?? $options['model'] ?? 'gpt-5.1-chat-latest';

        // Determine provider from model name
        if ($this->is_claude_model($model)) {
            return $this->call_claude($model, $options['claude_api_key'] ?? '', $system_prompt, $messages, $max_tokens);
        } elseif ($this->is_gemini_model($model)) {
            return $this->call_gemini($model, $options['gemini_api_key'] ?? '', $system_prompt, $messages, $max_tokens);
        } elseif ($this->is_xai_model($model)) {
            return $this->call_openai_compatible($model, $options['xai_api_key'] ?? '', 'https://api.x.ai/v1/chat/completions', $system_prompt, $messages, $max_tokens);
        } elseif ($this->is_deepseek_model($model)) {
            return $this->call_openai_compatible($model, $options['deepseek_api_key'] ?? '', 'https://api.deepseek.com/chat/completions', $system_prompt, $messages, $max_tokens);
        } else {
            // Default: OpenAI
            return $this->call_openai_compatible($model, $options['api_key'] ?? '', 'https://api.openai.com/v1/chat/completions', $system_prompt, $messages, $max_tokens);
        }
    }

    /**
     * Call OpenAI-compatible API (OpenAI, xAI, DeepSeek)
     */
    private function call_openai_compatible($model, $api_key, $endpoint, $system_prompt, $messages, $max_tokens) {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('API key not configured for the selected content model.', 'mxchat'));
        }

        $formatted = array();
        $formatted[] = array('role' => 'system', 'content' => $system_prompt);
        foreach ($messages as $msg) {
            $formatted[] = array(
                'role'    => $msg['role'] ?? 'user',
                'content' => $msg['content'] ?? '',
            );
        }

        // GPT-5.x models require max_completion_tokens and only support temperature=1
        $is_gpt5 = strpos($model, 'gpt-5') === 0;
        $token_key = $is_gpt5 ? 'max_completion_tokens' : 'max_tokens';

        $body = array(
            'model'       => $model,
            'messages'    => $formatted,
            $token_key    => $max_tokens,
            'stream'      => false,
        );

        if (!$is_gpt5) {
            $body['temperature'] = 0.7;
        }

        // Add reasoning_effort only for GPT-5 models that support it
        // gpt-5.2 and gpt-5.1-chat-latest don't support reasoning_effort parameter
        if ($is_gpt5 && $model !== 'gpt-5.2' && $model !== 'gpt-5.1-chat-latest') {
            // GPT-5.1 uses 'low' instead of 'minimal'
            if ($model === 'gpt-5.1-2025-11-13') {
                $body['reasoning_effort'] = 'low';
            } else {
                $body['reasoning_effort'] = 'minimal'; // For other GPT-5 models
            }
        }

        // Scale timeout with token count — large generation calls need more time
        $timeout = ($max_tokens > 8000) ? 300 : 120;

        $response = wp_remote_post($endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $decoded     = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $decoded['error']['message'] ?? __('API request failed with status ', 'mxchat') . $status_code;
            return new WP_Error('api_error', $error_msg);
        }

        if (isset($decoded['choices'][0]['message']['content'])) {
            return trim($decoded['choices'][0]['message']['content']);
        }

        return new WP_Error('unexpected_response', __('Unexpected API response format.', 'mxchat'));
    }

    /**
     * Call Claude (Anthropic) API
     */
    private function call_claude($model, $api_key, $system_prompt, $messages, $max_tokens) {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Claude API key not configured.', 'mxchat'));
        }

        $formatted = array();
        foreach ($messages as $msg) {
            $role = $msg['role'] ?? 'user';
            if (!in_array($role, array('user', 'assistant'), true)) {
                $role = 'user';
            }
            $formatted[] = array(
                'role'    => $role,
                'content' => $msg['content'] ?? '',
            );
        }

        $body = array(
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
            'messages'   => $formatted,
            'system'     => $system_prompt,
        );

        $timeout = ($max_tokens > 8000) ? 300 : 120;

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'Content-Type'     => 'application/json',
                'x-api-key'        => $api_key,
                'anthropic-version' => '2023-06-01',
            ),
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $decoded     = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $decoded['error']['message'] ?? __('Claude API error ', 'mxchat') . $status_code;
            return new WP_Error('claude_error', $error_msg);
        }

        if (isset($decoded['content'][0]['text'])) {
            return trim($decoded['content'][0]['text']);
        }

        return new WP_Error('unexpected_response', __('Unexpected Claude response format.', 'mxchat'));
    }


    /**
     * Call Gemini API
     */
    private function call_gemini($model, $api_key, $system_prompt, $messages, $max_tokens) {
        if (empty($api_key)) {
            return new WP_Error('no_api_key', __('Gemini API key not configured.', 'mxchat'));
        }

        $formatted = array();

        // System instructions as first user message
        $formatted[] = array(
            'role'  => 'user',
            'parts' => array(array('text' => "[System Instructions] " . $system_prompt)),
        );
        $formatted[] = array(
            'role'  => 'model',
            'parts' => array(array('text' => "I understand and will follow these instructions.")),
        );

        foreach ($messages as $msg) {
            $role = ($msg['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $formatted[] = array(
                'role'  => $role,
                'parts' => array(array('text' => $msg['content'] ?? '')),
            );
        }

        $body = array(
            'contents' => $formatted,
            'generationConfig' => array(
                'temperature'    => 0.7,
                'topP'           => 0.95,
                'topK'           => 40,
                'maxOutputTokens' => $max_tokens,
            ),
        );

        $api_version = (strpos($model, 'preview') !== false || strpos($model, 'exp') !== false) ? 'v1beta' : 'v1';
        $url = "https://generativelanguage.googleapis.com/{$api_version}/models/{$model}:generateContent?key={$api_key}";

        $timeout = ($max_tokens > 8000) ? 300 : 120;

        $response = wp_remote_post($url, array(
            'headers' => array('Content-Type' => 'application/json'),
            'body'    => wp_json_encode($body),
            'timeout' => $timeout,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $decoded     = json_decode(wp_remote_retrieve_body($response), true);

        if ($status_code !== 200) {
            $error_msg = $decoded['error']['message'] ?? __('Gemini API error ', 'mxchat') . $status_code;
            return new WP_Error('gemini_error', $error_msg);
        }

        if (isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($decoded['candidates'][0]['content']['parts'][0]['text']);
        }

        return new WP_Error('unexpected_response', __('Unexpected Gemini response format.', 'mxchat'));
    }

    // ─── Model Detection Helpers ────────────────────────────────────────

    private function is_claude_model($model) {
        return strpos($model, 'claude') === 0;
    }

    private function is_gemini_model($model) {
        return strpos($model, 'gemini') === 0;
    }

    private function is_xai_model($model) {
        return strpos($model, 'grok') === 0;
    }

    private function is_deepseek_model($model) {
        return strpos($model, 'deepseek') === 0;
    }

    // ─── Content Settings Save ────────────────────────────────────────

    /**
     * Dedicated handler for saving content generator settings.
     * Bypasses the main settings handler entirely.
     */
    public function handle_save_content_setting() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $field = sanitize_text_field($_POST['field'] ?? '');
        $value = sanitize_text_field($_POST['value'] ?? '');

        $allowed_fields = array('content_model', 'content_image_model', 'content_enable_images', 'content_use_placeholders', 'content_internal_linking', 'content_tool_use');
        if (!in_array($field, $allowed_fields, true)) {
            wp_send_json_error(array('message' => __('Invalid field.', 'mxchat')));
        }

        // Toggle fields
        if (in_array($field, array('content_enable_images', 'content_use_placeholders', 'content_internal_linking', 'content_tool_use'), true)) {
            $value = ($value === 'on') ? 'on' : 'off';
        }

        $options = get_option('mxchat_options', array());
        $options[$field] = $value;
        update_option('mxchat_options', $options);

        wp_send_json_success(array('message' => __('Setting saved.', 'mxchat')));
    }

    // ─── Progress Tracking ─────────────────────────────────────────────

    /**
     * Update generation progress.
     * Uses wp_options directly (not transients) to avoid object cache
     * stale-read issues across the background worker and polling processes.
     */
    private function update_progress($key, $step, $message, $percent, $result = null) {
        global $wpdb;

        $data = array(
            'step'    => $step,
            'message' => $message,
            'percent' => $percent,
            'updated' => time(),
        );
        if ($result !== null) {
            $data['result'] = $result;
        }

        $option_name = '_mxchat_progress_' . $key;
        $serialized  = maybe_serialize($data);

        // Direct DB write — bypasses object cache entirely
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $wpdb->options WHERE option_name = %s",
            $option_name
        ));

        if ($exists) {
            $wpdb->update(
                $wpdb->options,
                array('option_value' => $serialized),
                array('option_name' => $option_name)
            );
        } else {
            $wpdb->insert(
                $wpdb->options,
                array(
                    'option_name'  => $option_name,
                    'option_value' => $serialized,
                    'autoload'     => 'no',
                )
            );
        }

        // Also bust the object cache in case anything reads via get_option()
        wp_cache_delete($option_name, 'options');
    }

    /**
     * Read generation progress.
     * Direct DB read — bypasses object cache for guaranteed freshness.
     */
    private function get_progress($key) {
        global $wpdb;

        $option_name = '_mxchat_progress_' . $key;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM $wpdb->options WHERE option_name = %s",
            $option_name
        ));

        if ($value === null) {
            return false;
        }

        return maybe_unserialize($value);
    }

    /**
     * Delete generation progress (cleanup).
     */
    private function delete_progress($key) {
        global $wpdb;

        $option_name = '_mxchat_progress_' . $key;
        $wpdb->delete($wpdb->options, array('option_name' => $option_name));
        wp_cache_delete($option_name, 'options');
    }


    // ─── Content History ──────────────────────────────────────────────

    /**
     * Return paginated list of AI-generated posts for the History tab.
     */
    public function handle_content_history() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $page     = max(1, intval($_POST['page'] ?? 1));
        $per_page = 10;

        $query = new WP_Query(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => array('publish', 'draft', 'future', 'pending', 'private'),
            'meta_key'       => '_mxchat_generated',
            'meta_value'     => '1',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $items = array();
        foreach ($query->posts as $post) {
            $thumb = get_the_post_thumbnail_url($post->ID, 'thumbnail');
            $items[] = array(
                'post_id'   => $post->ID,
                'title'     => $post->post_title,
                'status'    => $post->post_status,
                'post_type' => $post->post_type,
                'date'      => get_the_date('M j, Y', $post),
                'thumbnail' => $thumb ? $thumb : '',
                'permalink' => get_permalink($post->ID),
            );
        }

        wp_send_json_success(array(
            'items'        => $items,
            'total'        => (int) $query->found_posts,
            'total_pages'  => (int) $query->max_num_pages,
            'current_page' => $page,
        ));
    }

    /**
     * Move an AI-generated post to the trash.
     */
    public function handle_delete_content() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'mxchat')));
        }

        // Only allow deleting MxChat-generated content
        if (get_post_meta($post_id, '_mxchat_generated', true) !== '1') {
            wp_send_json_error(array('message' => __('This post was not created by the content generator.', 'mxchat')));
        }

        $result = wp_trash_post($post_id);
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to delete post.', 'mxchat')));
        }

        wp_send_json_success(array('post_id' => $post_id));
    }

    /**
     * Update the status of an AI-generated post (draft, publish, future).
     */
    public function handle_update_post_status() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $post_id       = intval($_POST['post_id'] ?? 0);
        $new_status    = sanitize_text_field($_POST['new_status'] ?? '');
        $schedule_date = sanitize_text_field($_POST['schedule_date'] ?? '');

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'mxchat')));
        }

        // Only allow updating MxChat-generated content
        if (get_post_meta($post_id, '_mxchat_generated', true) !== '1') {
            wp_send_json_error(array('message' => __('This post was not created by the content generator.', 'mxchat')));
        }

        if (!in_array($new_status, array('draft', 'publish', 'future'), true)) {
            wp_send_json_error(array('message' => __('Invalid status.', 'mxchat')));
        }

        $post_args = array('ID' => $post_id, 'post_status' => $new_status);

        // Scheduled: require future date
        if ($new_status === 'future') {
            if (empty($schedule_date)) {
                wp_send_json_error(array('message' => __('A schedule date is required.', 'mxchat')));
            }
            $post_args['post_date']     = $schedule_date;
            $post_args['post_date_gmt'] = get_gmt_from_date($schedule_date);
            $post_args['edit_date']     = true;
        }

        // Transitioning FROM future to draft/publish: reset post_date to now
        if ($new_status !== 'future') {
            $current = get_post($post_id);
            if ($current && $current->post_status === 'future') {
                $post_args['post_date']     = current_time('mysql');
                $post_args['post_date_gmt'] = current_time('mysql', true);
                $post_args['edit_date']     = true;
            }
        }

        $result = wp_update_post($post_args, true);
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Re-read to get confirmed status (WP may auto-publish if schedule date is past)
        $updated = get_post($post_id);

        wp_send_json_success(array(
            'post_id' => $post_id,
            'status'  => $updated->post_status,
        ));
    }

    /**
     * Load an existing AI-generated post into the editor state.
     * Returns the same data shape as the generation success response.
     */
    public function handle_load_post_for_edit() {
        check_ajax_referer('mxchat_content_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'mxchat')));
        }

        $post_id = intval($_POST['post_id'] ?? 0);
        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid post ID.', 'mxchat')));
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(array('message' => __('Post not found.', 'mxchat')));
        }

        if (get_post_meta($post_id, '_mxchat_generated', true) !== '1') {
            wp_send_json_error(array('message' => __('This post was not created by the content generator.', 'mxchat')));
        }

        $preview_url = add_query_arg(array('preview' => 'true'), get_permalink($post_id));
        $edit_url    = admin_url('post.php?post=' . $post_id . '&action=edit');
        $permalink   = get_permalink($post_id);

        // Discover images — prefer stored IDs, fall back to post_parent query
        $images = $this->discover_post_images($post_id);

        wp_send_json_success(array(
            'post_id'     => $post_id,
            'preview_url' => $preview_url,
            'edit_url'    => $edit_url,
            'permalink'   => $permalink,
            'title'       => $post->post_title,
            'status'      => $post->post_status,
            'images'      => $images,
            'meta'        => array(
                'description' => get_post_meta($post_id, '_mxchat_meta_description', true),
                'keyword'     => $this->get_focus_keyword($post_id),
                'excerpt'     => $post->post_excerpt,
            ),
        ));
    }

    /**
     * Discover images associated with a generated post.
     * Uses stored attachment IDs (most reliable), falls back to post_parent query.
     */
    private function discover_post_images($post_id) {
        $images = array();
        $stored_ids = get_post_meta($post_id, '_mxchat_image_ids', true);

        if (!empty($stored_ids) && is_array($stored_ids)) {
            // Primary: use stored attachment IDs
            foreach ($stored_ids as $att_id) {
                $att_url = wp_get_attachment_url($att_id);
                if ($att_url) {
                    $thumb_url = wp_get_attachment_image_url($att_id, 'medium');
                    $images[] = array(
                        'url'           => $att_url,
                        'thumbnail'     => $thumb_url ?: $att_url,
                        'attachment_id' => $att_id,
                    );
                }
            }
        } else {
            // Fallback: query by post_parent + meta key
            $attachments = get_posts(array(
                'post_type'      => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'post_parent'    => $post_id,
                'meta_key'       => '_mxchat_image_prompt',
                'meta_compare'   => 'EXISTS',
                'orderby'        => 'date',
                'order'          => 'ASC',
            ));
            foreach ($attachments as $att) {
                $att_url = wp_get_attachment_url($att->ID);
                if ($att_url) {
                    $thumb_url = wp_get_attachment_image_url($att->ID, 'medium');
                    $images[] = array(
                        'url'           => $att_url,
                        'thumbnail'     => $thumb_url ?: $att_url,
                        'attachment_id' => $att->ID,
                    );
                }
            }
        }

        // Fallback: include featured image if nothing else found
        $featured_id = get_post_thumbnail_id($post_id);
        if (empty($images) && $featured_id) {
            $feat_url   = wp_get_attachment_url($featured_id);
            $feat_thumb = wp_get_attachment_image_url($featured_id, 'medium');
            if ($feat_url) {
                $images[] = array(
                    'url'           => $feat_url,
                    'thumbnail'     => $feat_thumb ?: $feat_url,
                    'attachment_id' => $featured_id,
                );
            }
        }

        return $images;
    }

    /**
     * Get the focus keyword (first keyword from comma-separated list).
     */
    private function get_focus_keyword($post_id) {
        $keywords = get_post_meta($post_id, '_mxchat_keywords', true);
        if (!empty($keywords)) {
            $parts = explode(',', $keywords);
            return trim($parts[0]);
        }
        return '';
    }
}