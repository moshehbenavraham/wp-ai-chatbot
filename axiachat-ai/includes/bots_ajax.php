<?php
/**
 * AI Chat - Bots AJAX (full schema)
 */

if ( ! defined('ABSPATH') ) { exit; }

// phpcs:disable WordPress.Security.NonceVerification.Missing -- All handlers call aichat_bots_check() which verifies the nonce.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uses trusted internal plugin tables via $wpdb->prefix.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only internal table operations.

add_action('wp_ajax_aichat_bots_list',     'aichat_bots_list');
add_action('wp_ajax_aichat_bot_create',    'aichat_bot_create');
add_action('wp_ajax_aichat_bot_update',    'aichat_bot_update');
add_action('wp_ajax_aichat_bot_duplicate', 'aichat_bot_duplicate');
add_action('wp_ajax_aichat_bot_reset',     'aichat_bot_reset');
add_action('wp_ajax_aichat_bot_delete',    'aichat_bot_delete');

function aichat_bots_table(){ global $wpdb; return $wpdb->prefix.'aichat_bots'; }
function aichat_bots_log($m,$ctx=[]){ aichat_log_debug('[AIChat Bots AJAX] '.$m.( $ctx? ' | '.wp_json_encode($ctx):'' )); }
function aichat_bots_check(){ if(!current_user_can('manage_options')){ wp_send_json_error(['message'=>'Forbidden'],403);} check_ajax_referer('aichat_bots_nonce','nonce'); }


function aichat_bots_insert_default(){
  global $wpdb;
  $table = aichat_bots_table();
  // Asegurar que la tabla existe antes de consultar
  if ( $wpdb->get_var( $wpdb->prepare("SHOW TABLES LIKE %s", $table ) ) !== $table ) {
    aichat_log_debug('Default bot insert: table not found (skip)');
    return;
  }

  // Si ya hay cualquier fila, no insertamos (más rápido que buscar slug)
  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
  $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM $table");
  if ( $count > 0 ) {
    aichat_log_debug('Default bot insert: skipped (rows exist='.$count.')');
    return;
  }

  // Verificación extra por slug (carrera muy improbable)
  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
  $exists = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $table WHERE slug=%s", 'default') );
  if ( $exists > 0 ) {
    aichat_log_debug('Default bot insert: skipped (default slug already present)');
    return;
  }

  $defaults = aichat_bots_defaults();
  $ok = $wpdb->insert($table, $defaults);
  if ( ! $ok ) {
    aichat_log_debug('Default bot insert: DB error', ['err'=>$wpdb->last_error]);
    return;
  }
  $bot_id = (int) $wpdb->insert_id;
  update_option('aichat_default_bot_seeded', 1, false);
  aichat_log_debug('Default bot insert: created id='.$bot_id);

  // Create an empty context for the default bot
  aichat_create_empty_context_for_bot( $bot_id, 'Default' );
}

/**
 * Create an empty context row and link it to a bot.
 *
 * Used when creating the default bot on activation and when creating
 * new bots via the admin UI, so every bot starts with its own context.
 *
 * @param int    $bot_id  Bot row ID.
 * @param string $bot_name  Bot name (used to derive context name).
 */
function aichat_create_empty_context_for_bot( $bot_id, $bot_name ) {
    global $wpdb;
    $ctx_table  = $wpdb->prefix . 'aichat_contexts';
    $bots_table = $wpdb->prefix . 'aichat_bots';

    // Derive a unique context name: "Default Context", "New Bot Context", etc.
    $base_name = trim( $bot_name ) !== '' ? trim( $bot_name ) : 'Bot';
    $ctx_name  = $base_name . ' Context';

    // Avoid duplicate names
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $dup = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ctx_table} WHERE name = %s", $ctx_name ) );
    if ( $dup > 0 ) {
        $ctx_name = $base_name . ' Context ' . ( $dup + 1 );
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $ok = $wpdb->insert(
        $ctx_table,
        [
            'name'                => $ctx_name,
            'context_type'        => 'local',
            'processing_status'   => 'pending',
            'processing_progress' => 0,
        ],
        [ '%s', '%s', '%s', '%d' ]
    );

    if ( ! $ok ) {
        aichat_log_debug( 'aichat_create_empty_context_for_bot: INSERT failed for bot_id=' . $bot_id );
        return;
    }

    $context_id = (int) $wpdb->insert_id;

    // Link bot → context
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
    $wpdb->update(
        $bots_table,
        [ 'context_id' => $context_id, 'context_mode' => 'embeddings' ],
        [ 'id' => $bot_id ],
        [ '%d', '%s' ],
        [ '%d' ]
    );

    aichat_log_debug( "aichat_create_empty_context_for_bot: created context id=$context_id name='$ctx_name' for bot_id=$bot_id" );
}

function aichat_bots_defaults($over=[]){
  $now = current_time('mysql');
  $def_model = aichat_get_default_model('openai');
  $d = [
  'name' => 'Default', 'slug'=>'default', 'type'=>'text', 'instructions'=>"Act as a Customer Service Specialist:\n- Respond promptly to user questions.\n- Resolve issues efficiently and accurately.\n- Provide helpful and clear solutions.\n- Maintain a friendly and professional demeanor at all times.\n- Use a professional tone and give detailed, well-structured explanations.\n\nTry to answer briefly, ideally in one or two concise sentences. If the user asks for more detail, then elaborate.",
    'provider'=>'openai','model'=>$def_model,'temperature'=>0.70,'max_tokens'=>2048,'reasoning'=>'off','verbosity'=>'medium',
  'context_mode'=>'page','context_id'=>0,
    'input_max_length'=>512,'max_messages'=>20,'context_max_length'=>4096,
  'ui_color'=>'#1a73e8','ui_position'=>'br','ui_avatar_enabled'=>1,'ui_avatar_key'=>'7','ui_icon_url'=>'','ui_start_sentence'=>'Hi! How can I help you?',
    /* nuevos por defecto */
    'ui_placeholder'  => 'Write your question...',
    'ui_button_send'  => 'Send',
  'ui_width'             => 380,
  'ui_height'            => 380,
    'ui_closable'           => 1,
    'ui_minimizable'        => 1,
    'ui_draggable'          => 1,
    'ui_minimized_default'  => 0,
  'ui_superminimized_default' => 0,
  'ui_avatar_bubble' => 1,
  'ui_css_force' => 0,
    /* suggestions / next actions */
    'ui_suggestions_enabled' => 1,
    'ui_suggestions_count'   => 3,
    'ui_suggestions_bg'      => '#f1f3f4',
    'ui_suggestions_text'    => '#1a73e8',
    'ui_role' => 'AI Agent Specialist',
    /* default enabled capabilities */
    'tools_json' => wp_json_encode( [ 'lead_capture', 'appointment_booking', 'notifications_email_admin' ] ),
    'is_active'=>1,'created_at'=>$now,'updated_at'=>$now
  ];
  return array_merge($d,$over);
}

function aichat_bots_unique_slug($slug,$exclude=0){
  global $wpdb; $t=aichat_bots_table();
  $base = sanitize_title($slug); if($base==='') $base='bot';
  $try=$base; $i=2;
  while(true){
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
    $cnt = (int)$wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM $t WHERE slug=%s AND id<>%d",$try,(int)$exclude) );
    if($cnt===0) return $try;
    $try = $base.'-'.$i; $i++; if($i>9999) return $base.'-'.time();
  }
}

function aichat_bots_sanitize_patch($patch,$row=null){
  $out=[];

  // alias legacy
  if (isset($patch['label']) && !isset($patch['name'])) $patch['name']=$patch['label'];
  if (isset($patch['mode'])  && !isset($patch['type'])) $patch['type']=$patch['mode'];

  // básicos
  if (isset($patch['name']))         $out['name'] = sanitize_text_field($patch['name']);
  if (isset($patch['slug']))         $out['slug'] = sanitize_title($patch['slug']);
  if (isset($patch['instructions'])) $out['instructions'] = sanitize_textarea_field($patch['instructions']);
  if (isset($patch['type'])) {
    $t = sanitize_text_field($patch['type']);
    $out['type'] = in_array($t,['text','voice_text'],true) ? $t : 'text';
  }

  // modelo
  if (isset($patch['provider']))  $out['provider']  = sanitize_text_field($patch['provider']);
  if (isset($patch['model']))     $out['model']     = sanitize_text_field($patch['model']);
  if (isset($patch['temperature'])) {
    $temp = floatval($patch['temperature']); if ($temp<0) $temp=0; if($temp>2) $temp=2; $out['temperature']=$temp;
  }
  if (isset($patch['max_tokens']))       $out['max_tokens'] = max(1, intval($patch['max_tokens']));
  if (isset($patch['reasoning'])) {
    $r = sanitize_text_field($patch['reasoning']);
    $out['reasoning'] = in_array($r,['off','fast','accurate'],true)?$r:'off';
  }
  if (isset($patch['verbosity'])) {
    $v = sanitize_text_field($patch['verbosity']);
    $out['verbosity'] = in_array($v,['low','medium','high'],true)?$v:'medium';
  }

  // contexto
  if (isset($patch['context_mode'])) {
    $cm = sanitize_text_field($patch['context_mode']);
    $out['context_mode'] = in_array($cm,['embeddings','page','none'],true)?$cm:'embeddings';
  }
  if (isset($patch['context_id'])) $out['context_id'] = max(0, intval($patch['context_id']));

  // thresholds
  if (isset($patch['input_max_length']))    $out['input_max_length'] = max(1,intval($patch['input_max_length']));
  if (isset($patch['max_messages']))        $out['max_messages']     = max(1,intval($patch['max_messages']));
  if (isset($patch['context_max_length']))  $out['context_max_length']= max(128,intval($patch['context_max_length']));
  if (isset($patch['context_limit']))       { $n = intval($patch['context_limit']); $out['context_limit'] = max(3, min(10, $n)); }
  if (isset($patch['history_persistence'])) $out['history_persistence'] = intval(!!$patch['history_persistence']);

  // UI
  if (isset($patch['ui_color']))        $out['ui_color'] = preg_match('/^#[0-9a-fA-F]{6}$/',$patch['ui_color']) ? $patch['ui_color'] : '#1a73e8';
  if (isset($patch['ui_position'])) {
    $p = sanitize_text_field($patch['ui_position']);
    $out['ui_position'] = in_array($p,['br','bl','tr','tl'],true)?$p:'br';
  }
  if (isset($patch['ui_avatar_enabled'])) $out['ui_avatar_enabled'] = intval(!!$patch['ui_avatar_enabled']);
  if (isset($patch['ui_avatar_key']))     $out['ui_avatar_key']     = sanitize_text_field($patch['ui_avatar_key']);
  if (isset($patch['ui_icon_url']))       $out['ui_icon_url']       = esc_url_raw($patch['ui_icon_url']);
  if (isset($patch['ui_start_sentence'])) $out['ui_start_sentence'] = sanitize_text_field($patch['ui_start_sentence']);
  if (isset($patch['ui_role']))           $out['ui_role']           = sanitize_text_field($patch['ui_role']);

  /* nuevos campos UI */
  if (isset($patch['ui_placeholder']))    $out['ui_placeholder']    = sanitize_text_field($patch['ui_placeholder']);
  if (isset($patch['ui_button_send']))    $out['ui_button_send']    = sanitize_text_field($patch['ui_button_send']);
  if (isset($patch['ui_width']))          { $w = intval($patch['ui_width']);  $w = max(300, min(1200, $w)); $out['ui_width'] = $w; }
  if (isset($patch['ui_height']))         { $h = intval($patch['ui_height']); $h = max(300, min(1200, $h)); $out['ui_height'] = $h; }
  if (isset($patch['ui_closable']))           $out['ui_closable']          = intval(!!$patch['ui_closable']);
  if (isset($patch['ui_minimizable']))        $out['ui_minimizable']       = intval(!!$patch['ui_minimizable']);
  if (isset($patch['ui_draggable']))          $out['ui_draggable']         = intval(!!$patch['ui_draggable']);
  if (isset($patch['ui_minimized_default']))  $out['ui_minimized_default'] = intval(!!$patch['ui_minimized_default']);
  if (isset($patch['ui_superminimized_default']))  $out['ui_superminimized_default'] = intval(!!$patch['ui_superminimized_default']);
  if (isset($patch['ui_avatar_bubble']))             $out['ui_avatar_bubble']          = intval(!!$patch['ui_avatar_bubble']);
  if (isset($patch['ui_css_force']))                $out['ui_css_force']              = intval(!!$patch['ui_css_force']);

  // Suggestions / next actions
  if (isset($patch['ui_suggestions_enabled'])) $out['ui_suggestions_enabled'] = intval(!!$patch['ui_suggestions_enabled']);
  if (isset($patch['ui_suggestions_count']))  { $n = intval($patch['ui_suggestions_count']); $n = max(1, min(6, $n)); $out['ui_suggestions_count'] = $n; }
  if (isset($patch['ui_suggestions_bg']))     $out['ui_suggestions_bg']   = preg_match('/^#[0-9a-fA-F]{6}$/',$patch['ui_suggestions_bg']) ? $patch['ui_suggestions_bg'] : '#f1f3f4';
  if (isset($patch['ui_suggestions_text']))   $out['ui_suggestions_text'] = preg_match('/^#[0-9a-fA-F]{6}$/',$patch['ui_suggestions_text']) ? $patch['ui_suggestions_text'] : '#1a73e8';

  // Coherencia provider ↔ modelo (uses centralised model registry)
  if (isset($out['provider']) || isset($out['model'])) {
    $prov = isset($out['provider']) ? $out['provider'] : ($row['provider'] ?? 'openai');
    $model = isset($out['model']) ? $out['model'] : ($row['model'] ?? '');

    // Resolve aliases, deprecated ids, and prefix matches via registry
    $resolved = aichat_resolve_model( $model, $prov );

    // Verify the resolved model belongs to the chosen provider
    $reg = aichat_get_model_registry();
    if ( isset( $reg[ $resolved ] ) ) {
      $model_prov = $reg[ $resolved ]['provider'];
      // Normalise 'claude' → 'anthropic' for comparison
      $norm_prov = ( $prov === 'claude' ) ? 'anthropic' : $prov;
      if ( $model_prov !== $norm_prov ) {
        // Model doesn't belong to this provider — fall back to provider default
        $resolved = aichat_get_default_model( $prov );
      }
    }

    $out['model'] = $resolved;
  }

  return $out;
}

function aichat_bots_cast_row($r){
  if (!is_array($r)) return $r;
  $ints   = ['id','context_id','max_tokens','input_max_length','max_messages','context_max_length','is_active','ui_suggestions_count'];
  $bools  = ['ui_avatar_enabled','ui_closable','ui_minimizable','ui_draggable','ui_minimized_default','ui_superminimized_default','ui_avatar_bubble','ui_css_force','ui_suggestions_enabled'];
  $floats = ['temperature'];
  foreach($ints as $k){ if(isset($r[$k])) $r[$k] = (int)$r[$k]; }
  foreach($bools as $k){ if(isset($r[$k])) $r[$k] = (int)!empty($r[$k]); }
  foreach($floats as $k){ if(isset($r[$k])) $r[$k] = (float)$r[$k]; }
  return $r;
}
 
/* ---------- LIST ---------- */
function aichat_bots_list(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $rows = $wpdb->get_results("SELECT * FROM $t ORDER BY id ASC", ARRAY_A);
  $rows = array_map('aichat_bots_cast_row', (array)$rows);

  if (empty($rows)) {
    $def = aichat_bots_defaults();
    $def['slug'] = aichat_bots_unique_slug($def['slug'], 0);
    $wpdb->insert($t, $def);
    $id = (int)$wpdb->insert_id;
    $rows = [ array_merge(['id'=>$id], aichat_bots_cast_row($def)) ];
  }

  $out = array_map(function($r){
    $r['label'] = $r['name'];
    $r['mode']  = $r['type'];
    $r['is_default'] = ($r['slug']==='default') ? 1 : 0;
    return $r;
  }, $rows);

  // Add limit info for frontend
  $max_bots = function_exists( 'aichat_max_bots_allowed' ) ? aichat_max_bots_allowed() : 1;
  $can_create = count( $out ) < $max_bots;
  $upgrade_url = function_exists( 'aichat_get_upgrade_url' ) ? aichat_get_upgrade_url() : 'https://axiachat.com/pricing/';

  aichat_bots_log('LIST ok', ['count'=>count($out)]);
  wp_send_json_success([
    'bots' => $out,
    'limits' => [
      'max_bots' => $max_bots === PHP_INT_MAX ? null : $max_bots,
      'current_count' => count( $out ),
      'can_create' => $can_create,
      'upgrade_url' => $upgrade_url,
    ],
    'global_bot_enabled' => (int) get_option( 'aichat_global_bot_enabled', 0 ),
    'global_bot_slug'    => get_option( 'aichat_global_bot_slug', '' ),
  ]);
}

/* ---------- CREATE ---------- */
function aichat_bot_create(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // Check bot limit for current plan
  $max_bots = function_exists( 'aichat_max_bots_allowed' ) ? aichat_max_bots_allowed() : 1;
  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $current_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
  if ( $current_count >= $max_bots ) {
    $upgrade_url = function_exists( 'aichat_get_upgrade_url' ) ? aichat_get_upgrade_url() : 'https://axiachat.com/pricing/';
    wp_send_json_error([
      'message' => sprintf(
        /* translators: %1$d: max bots allowed, %2$s: upgrade URL */
        __( 'You have reached the limit of %1$d chatbot(s) on your current plan. <a href="%2$s" target="_blank">Upgrade your plan</a> for more chatbots.', 'axiachat-ai' ),
        $max_bots,
        esc_url( $upgrade_url )
      ),
      'limit_reached' => true,
      'upgrade_url' => $upgrade_url
    ], 403);
  }

  $now = current_time('mysql');
  $row = aichat_bots_defaults([
    'name'=>'New Bot','slug'=>'new-bot','type'=>'text','created_at'=>$now,'updated_at'=>$now
  ]);
  $row['slug'] = aichat_bots_unique_slug($row['slug'], 0);

  $ok = $wpdb->insert($t,$row);
  if (!$ok) { aichat_bots_log('CREATE error', ['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB insert error'],500); }

  $id = (int)$wpdb->insert_id;

  // Create an empty context for the new bot and link it
  aichat_create_empty_context_for_bot( $id, $row['name'] );

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $r  = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $r  = aichat_bots_cast_row($r);
  $r['label']=$r['name']; $r['mode']=$r['type']; $r['is_default']=($r['slug']==='default')?1:0;

  aichat_bots_log('CREATE ok',['id'=>$id]);
  wp_send_json_success($r);
}

/* ---------- UPDATE ---------- */
function aichat_bot_update(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in aichat_bots_check().
  $id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$row) wp_send_json_error(['message'=>'Bot not found'],404);

  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in aichat_bots_check().
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON patch validated by aichat_validate_patch_payload().
  $raw = isset($_POST['patch']) ? wp_unslash( $_POST['patch'] ) : '{}';
  $patch = aichat_validate_patch_payload( $raw );
  if ( is_wp_error( $patch ) ) {
    wp_send_json_error( [ 'message' => $patch->get_error_message() ], 400 );
  }
  if ( ! is_array( $patch ) ) { $patch = []; }

  $data = aichat_bots_sanitize_patch($patch, $row);

  if (isset($data['slug'])) $data['slug'] = aichat_bots_unique_slug($data['slug'], $id);

  // Handle global bot toggle (synced with Settings > General option)
  if ( isset( $patch['_global_bot_enabled'] ) ) {
    $enabled = intval( !! $patch['_global_bot_enabled'] );
    update_option( 'aichat_global_bot_enabled', $enabled );
    $slug = isset( $data['slug'] ) ? $data['slug'] : $row['slug'];
    if ( $enabled ) {
      update_option( 'aichat_global_bot_slug', sanitize_title( $slug ) );
    }
  }

  if (empty($data)) { aichat_bots_log('UPDATE noop',['id'=>$id]); wp_send_json_success(['updated'=>false,'id'=>$id]); }

  $data['updated_at'] = current_time('mysql');

  aichat_bots_log('UPDATE', ['id'=>$id,'fields'=>array_keys($data)]);
  $ok = $wpdb->update($t, $data, ['id'=>$id]);
  if ($ok===false) { aichat_bots_log('UPDATE error',['db_error'=>$wpdb->last_error]); wp_send_json_error( [ 'message' => __( 'Database update error.', 'axiachat-ai' ) ], 500 ); }

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $r = aichat_bots_cast_row($r);
  $r['label']=$r['name']; $r['mode']=$r['type']; $r['is_default']=($r['slug']==='default')?1:0;
  wp_send_json_success(['updated'=>true,'bot'=>$r]);
}

/* ---------- DUPLICATE ---------- */
function aichat_bot_duplicate(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // Check bot limit for current plan
  $max_bots = function_exists( 'aichat_max_bots_allowed' ) ? aichat_max_bots_allowed() : 1;
  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $current_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t" );
  if ( $current_count >= $max_bots ) {
    $upgrade_url = function_exists( 'aichat_get_upgrade_url' ) ? aichat_get_upgrade_url() : 'https://axiachat.com/pricing/';
    wp_send_json_error([
      'message' => sprintf(
        /* translators: %1$d: max bots allowed, %2$s: upgrade URL */
        __( 'You have reached the limit of %1$d chatbot(s) on your current plan. <a href="%2$s" target="_blank">Upgrade your plan</a> for more chatbots.', 'axiachat-ai' ),
        $max_bots,
        esc_url( $upgrade_url )
      ),
      'limit_reached' => true,
      'upgrade_url' => $upgrade_url
    ], 403);
  }

  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in aichat_bots_check().
  $id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  $now = current_time('mysql');
  $copy = $r; unset($copy['id']); // copia todo
  $copy['name'] = trim($r['name'])!=='' ? $r['name'].' Copy' : 'Bot Copy';
  $copy['slug'] = aichat_bots_unique_slug(($r['slug']?:'bot').'-copy',0);
  $copy['created_at'] = $now; $copy['updated_at'] = $now;

  $ok = $wpdb->insert($t, $copy);
  if (!$ok) { aichat_bots_log('DUP error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB insert error'],500); }

  $new_id = (int)$wpdb->insert_id;
  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $nr = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$new_id), ARRAY_A );
  $nr = aichat_bots_cast_row($nr);
  $nr['label']=$nr['name']; $nr['mode']=$nr['type']; $nr['is_default']=($nr['slug']==='default')?1:0;
  wp_send_json_success($nr);
}

/* ---------- RESET ---------- */
function aichat_bot_reset(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in aichat_bots_check().
  $id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $r = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  // Mantener slug, resetear el resto a defaults
  $now = current_time('mysql');
  $d = aichat_bots_defaults();
  unset($d['slug'], $d['created_at']); // preservamos slug y created_at original
  $d['name'] = ($r['slug']==='default') ? 'Default' : 'New Bot';
  $d['updated_at'] = $now;

  $ok = $wpdb->update($t, $d, ['id'=>$id]);
  if ($ok===false) { aichat_bots_log('RESET error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB update error'],500); }

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $nr = $wpdb->get_row( $wpdb->prepare("SELECT * FROM $t WHERE id=%d",$id), ARRAY_A );
  $nr = aichat_bots_cast_row($nr);
  $nr['label']=$nr['name']; $nr['mode']=$nr['type']; $nr['is_default']=($nr['slug']==='default')?1:0;
  wp_send_json_success($nr);
}

/* ---------- DELETE ---------- */
function aichat_bot_delete(){
  aichat_bots_check(); aichat_bots_maybe_create();
  global $wpdb; $t=aichat_bots_table();

  // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in aichat_bots_check().
  $id = isset($_POST['id']) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
  if ($id<=0) wp_send_json_error(['message'=>'Missing id'],400);

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $r = $wpdb->get_row( $wpdb->prepare("SELECT id,slug FROM $t WHERE id=%d",$id), ARRAY_A );
  if (!$r) wp_send_json_error(['message'=>'Bot not found'],404);

  // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $t is a trusted plugin table name via aichat_bots_table().
  $total = (int)$wpdb->get_var("SELECT COUNT(*) FROM $t");
  if ($total<=1) wp_send_json_error(['message'=>'Cannot delete the only bot'],400);
  if ($r['slug']==='default') wp_send_json_error(['message'=>'Cannot delete default bot'],400);

  $ok = $wpdb->delete($t, ['id'=>$id], ['%d']);
  if (!$ok) { aichat_bots_log('DELETE error',['db_error'=>$wpdb->last_error]); wp_send_json_error(['message'=>'DB delete error'],500); }

  wp_send_json_success(['deleted'=>true,'id'=>$id]);
}

// phpcs:enable WordPress.Security.NonceVerification.Missing
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
