<?php
if ( ! defined('ABSPATH') ) { exit; }

// Admin-only add-on endpoints; uses direct DB reads/writes for internal tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

add_action('wp_ajax_aichat_tools_get_rules', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  $map = get_option('aichat_tools_rules_map','{}');
  $decoded = json_decode($map,true); if(!is_array($decoded)) $decoded = [];
  $rules = isset($decoded[$bot]) && is_array($decoded[$bot]) ? $decoded[$bot] : [];
  wp_send_json_success(['rules'=>$rules]);
});

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

add_action('wp_ajax_aichat_tools_save_rules', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and sanitized per-field below
  $raw = isset($_POST['rules']) ? wp_unslash($_POST['rules']) : '[]';
  $arr = json_decode($raw,true); if(!is_array($arr)) $arr = [];
  $clean = [];
  foreach($arr as $r){ if(!is_array($r)) continue; $when = isset($r['when']) && is_array($r['when']) ? array_values($r['when']) : [];
    $actions = isset($r['actions']) && is_array($r['actions']) ? array_values($r['actions']) : [];
    $clean[] = [ 'when'=>$when, 'actions'=>$actions ]; }
  $map = get_option('aichat_tools_rules_map','{}'); $decoded = json_decode($map,true); if(!is_array($decoded)) $decoded = [];
  $decoded[$bot] = $clean; update_option('aichat_tools_rules_map', wp_json_encode($decoded));
  wp_send_json_success(['saved'=>true,'count'=>count($clean),'bot'=>$bot]);
});

add_action('wp_ajax_aichat_tools_get_bot_tools', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  global $wpdb; $bot_slug = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot_slug==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
    $bots_table = $wpdb->prefix.'aichat_bots';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only internal table read.
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is derived from $wpdb->prefix; values use placeholders
  $row = $wpdb->get_row( $wpdb->prepare("SELECT tools_json FROM {$bots_table} WHERE slug=%s", $bot_slug), ARRAY_A );
  $selected = [];
  if($row && !empty($row['tools_json'])){ $tmp = json_decode((string)$row['tools_json'], true); if(is_array($tmp)) $selected = array_values(array_filter($tmp, 'is_string')); }
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  $tools  = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
  wp_send_json_success(['selected'=>$selected,'macros'=>$macros,'tools'=>$macros?[]:$tools]);
});

add_action('wp_ajax_aichat_tools_save_bot_tools', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  global $wpdb; $bot_slug = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot_slug==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and validated below
  $raw = isset($_POST['selected']) ? wp_unslash($_POST['selected']) : '[]'; $arr = json_decode($raw, true); if(!is_array($arr)) $arr=[];
  $macros = function_exists('aichat_get_registered_macros') ? aichat_get_registered_macros() : [];
  $tools  = function_exists('aichat_get_registered_tools') ? aichat_get_registered_tools() : [];
  $valid_macro_names = array_keys($macros); $valid_tool_names  = array_keys($tools);
  $clean = [];
  foreach($arr as $id){ if(!is_string($id)) continue; $id = sanitize_key($id);
    if ( in_array($id,$valid_macro_names,true) || in_array($id,$valid_tool_names,true) ) {
      if(!in_array($id,$clean,true)) $clean[] = $id; }
  }
    $bots_table = $wpdb->prefix.'aichat_bots';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only internal table update.
  $updated = $wpdb->update($bots_table, [ 'tools_json' => wp_json_encode($clean) ], [ 'slug'=>$bot_slug ] );
  if($updated===false){ wp_send_json_error(['message'=>'db_error']); }
  wp_send_json_success(['saved'=>true,'selected'=>$clean,'bot'=>$bot_slug]);
});

// === Capability settings (per bot, per capability) ===
add_action('wp_ajax_aichat_tools_get_capability_settings', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  if ($bot==='') { wp_send_json_error(['message'=>'missing_bot'],400); }
  if ( ! function_exists('aichat_get_capability_settings_for_bot') ) { wp_send_json_error(['message'=>'api_missing'],500); }
  $settings = aichat_get_capability_settings_for_bot($bot);
  if (!is_array($settings)) { $settings = []; }
  wp_send_json_success(['settings' => $settings, 'bot'=>$bot]);
});

add_action('wp_ajax_aichat_tools_save_capability_settings', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $bot = isset($_POST['bot']) ? sanitize_title(wp_unslash($_POST['bot'])) : '';
  $cap = isset($_POST['cap']) ? sanitize_key(wp_unslash($_POST['cap'])) : '';
  if ($bot==='' || $cap==='') { wp_send_json_error(['message'=>'missing_params'],400); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON payload is decoded and sanitized per-field below
  $raw = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : '{}';
  $arr = json_decode($raw, true); if(!is_array($arr)) $arr = [];
  // Sanitize known fields
  $clean_cap = [];
  // Optional domains allowlist for web search capability
  if (isset($arr['domains']) && is_array($arr['domains'])) {
    $doms = [];
    foreach ($arr['domains'] as $d) {
      $d = trim((string)$d);
      if ($d === '') continue;
      // keep host-ish strings: letters, digits, dots, dashes
      $d = preg_replace('/[^a-z0-9\.-]/i', '', $d);
      if ($d !== '' && !in_array($d,$doms,true)) $doms[] = $d;
    }
    // Persist even if empty to allow clearing previous settings
    $clean_cap['domains'] = $doms;
  }
  if ( ! function_exists('aichat_get_capability_settings_map') || ! function_exists('aichat_save_capability_settings_for_bot') ) {
    wp_send_json_error(['message'=>'api_missing'],500);
  }
  $all = aichat_get_capability_settings_map(); if(!is_array($all)) $all = [];
  if (!isset($all[$bot]) || !is_array($all[$bot])) { $all[$bot] = []; }
  if (!isset($all[$bot][$cap]) || !is_array($all[$bot][$cap])) { $all[$bot][$cap] = []; }
  // Merge new fields into existing cap settings
  $all[$bot][$cap] = array_merge($all[$bot][$cap], $clean_cap);
  aichat_save_capability_settings_for_bot($bot, $all[$bot]);
  wp_send_json_success(['saved'=>true,'bot'=>$bot,'cap'=>$cap,'settings'=>$all[$bot][$cap]]);
});

// === Test Tools: list and run underlying tools ===
add_action('wp_ajax_aichat_tools_list_all_tools', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  if ( ! function_exists('aichat_get_registered_tools') ) { wp_send_json_error(['message'=>'api_missing'],500); }
  $tools = aichat_get_registered_tools();
  // Filter: only atomic function tools for now
  $out = [];
  foreach($tools as $id=>$def){
    if (($def['type'] ?? '') !== 'function') continue;
    $out[$id] = [
      'name' => $def['name'] ?? $id,
      'description' => $def['description'] ?? '',
      'schema' => $def['schema'] ?? [],
    ];
  }
  wp_send_json_success(['tools'=>$out]);
});

add_action('wp_ajax_aichat_tools_run_tool', function(){
  if ( ! current_user_can('manage_options') ) { wp_send_json_error(['message'=>'forbidden'],403); }
  check_ajax_referer('aichat_tools_nonce','nonce');
  $tool_id = isset($_POST['tool']) ? sanitize_key(wp_unslash($_POST['tool'])) : '';
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- arguments JSON parsed & sanitized
  $raw_args = isset($_POST['args']) ? wp_unslash($_POST['args']) : '{}';
  $args = json_decode($raw_args, true); if(!is_array($args)) $args = [];
  if ($tool_id==='') { wp_send_json_error(['message'=>'missing_tool'],400); }
  if ( ! function_exists('aichat_get_registered_tools') ) { wp_send_json_error(['message'=>'api_missing'],500); }
  $tools = aichat_get_registered_tools();
  if ( ! isset($tools[$tool_id]) ) { wp_send_json_error(['message'=>'unknown_tool'],400); }
  $def = $tools[$tool_id];
  if ( ($def['type'] ?? '') !== 'function' || ! is_callable($def['callback']) ) { wp_send_json_error(['message'=>'not_callable'],400); }
  // Best-effort sanitization against declared schema types
  if ( isset($def['schema']) && is_array($def['schema']) && isset($def['schema']['properties']) ) {
    $props = $def['schema']['properties'];
    if ( is_array($props) || is_object($props) ) {
      foreach($props as $k=>$spec){
        $v = isset($args[$k]) ? $args[$k] : null;
        $t = is_array($spec) && isset($spec['type']) ? $spec['type'] : '';
        switch($t){
          case 'integer': $args[$k] = is_numeric($v) ? (int)$v : 0; break;
          case 'number': $args[$k] = is_numeric($v) ? (float)$v : 0; break;
          case 'boolean': $args[$k] = (bool)$v; break;
          case 'string': $args[$k] = is_string($v)? sanitize_text_field($v) : ( is_scalar($v)? (string)$v : '' ); break;
          case 'object': $args[$k] = is_array($v)? $v : []; break;
          case 'array': $args[$k] = is_array($v)? array_values($v) : []; break;
          default: /* leave as is */ break;
        }
      }
    }
  }
  try {
    $out = call_user_func( $def['callback'], $args, [ 'source'=>'admin_test_tools' ] );
    if ( is_wp_error($out) ) {
      wp_send_json_success(['ok'=>false,'error'=>$out->get_error_code(),'message'=>$out->get_error_message(),'result'=>null]);
    }
    wp_send_json_success(['ok'=>true,'result'=>$out]);
  } catch( \Throwable $e ) {
    wp_send_json_success(['ok'=>false,'error'=>'exception','message'=>$e->getMessage()]);
  }
});
