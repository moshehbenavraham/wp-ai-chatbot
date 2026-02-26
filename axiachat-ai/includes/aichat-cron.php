<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// This module runs on WP-Cron / Action Scheduler and performs internal maintenance.
// Caching DB reads/writes here is not beneficial and can lead to stale state.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/* ==============================
   Configuración
============================== */
if ( ! defined( 'AICHAT_BATCH_SIZE' ) ) define( 'AICHAT_BATCH_SIZE', 40 );
if ( ! defined( 'AICHAT_LOCK_TTL' ) )   define( 'AICHAT_LOCK_TTL', 300 ); // 5 min

add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['oneminute'] = ['interval'=>60,'display'=>__('Every 1 Minute','axiachat-ai')];
    return $schedules;
});

/* ==============================
   Helpers (keys/options)
============================== */
if ( ! function_exists('aichat_lock_key') ) {
    function aichat_lock_key( $id ){ return 'aichat_processing_'.(int)$id; }
}
if ( ! function_exists('aichat_lock_start_key') ) {
    function aichat_lock_start_key( $id ){ return 'aichat_processing_start_'.(int)$id; }
}
if ( ! function_exists('aichat_total_items_key') ) {
    function aichat_total_items_key( $id ){ return 'aichat_total_items_'.(int)$id; }
}
if ( ! function_exists('aichat_cursor_key') ) {
    function aichat_cursor_key( $id ){ return 'aichat_cursor_index_'.(int)$id; }
}
if ( ! function_exists('aichat_last_post_key') ) {
    function aichat_last_post_key( $id ){ return 'aichat_last_post_id_'.(int)$id; }
}

function aichat_mark_completed( $context_id ){
    global $wpdb;
    $wpdb->update($wpdb->prefix.'aichat_contexts',
        ['processing_status'=>'completed','processing_progress'=>100],
        ['id'=>(int)$context_id]
    );
    delete_option( aichat_total_items_key($context_id) );
    delete_option( aichat_cursor_key($context_id) );
    delete_option( aichat_last_post_key($context_id) );
    aichat_log_debug("Context $context_id marked as completed.");
}

function aichat_schedule_single( $timestamp, $hook, array $args=[], $group='aichat' ){
    $has_as = class_exists('Action_Scheduler') && function_exists('as_schedule_single_action') && function_exists('as_next_scheduled_action');
    if ( ! wp_next_scheduled($hook,$args) ) {
        wp_schedule_single_event($timestamp,$hook,$args);
    }
}

/**
 * Deduplicación estable (preserva orden) + saneo (ints > 0)
 */
if ( ! function_exists('aichat_stable_unique_ids') ) {
    function aichat_stable_unique_ids( $arr ){
        $out=[]; $seen=[];
        if(!is_array($arr)) return $out;
        foreach($arr as $v){
            $id = (int)$v;
            if($id<=0) continue;
            $k = (string)$id;
            if(isset($seen[$k])) continue;
            $seen[$k]=true;
            $out[]=$id;
        }
        return $out;
    }
}

/**
 * Normaliza items_to_process: quita duplicados, actualiza BD y total_items (únicos).
 * Ajusta el cursor si quedó fuera de rango.
 * Devuelve [items_unicos, total_unicos]
 */
function aichat_normalize_items_and_totals( $context ){
    global $wpdb;
    $context_id = (int)$context['id'];

    $raw = maybe_unserialize( $context['items_to_process'] );
    $raw = is_array($raw) ? $raw : [];
    $items_unique = aichat_stable_unique_ids( $raw );
    $unique_total = count( $items_unique );

    // Persistimos la versión sin duplicados si cambió
    if ( $unique_total !== count($raw) ) {
        $wpdb->update(
            $wpdb->prefix.'aichat_contexts',
            [ 'items_to_process' => maybe_serialize($items_unique) ],
            [ 'id' => $context_id ]
        );
    aichat_log_debug("ctx $context_id de-duplicated", ['original_count'=>count($raw), 'unique_total'=>$unique_total]);
    }

    // Total fijo a los únicos
    $total_key = aichat_total_items_key($context_id);
    $prev_total = (int)get_option( $total_key, 0 );
    if ( $prev_total !== $unique_total ) {
        update_option( $total_key, $unique_total, false );
    aichat_log_debug("ctx $context_id total_items updated", ['total_unique'=>$unique_total]);
    }

    // Ajustar cursor si queda fuera de rango
    $cursor_key = aichat_cursor_key($context_id);
    $cursor = (int)get_option($cursor_key, 0);
    if ( $cursor > $unique_total ) {
        $cursor = $unique_total;
        update_option($cursor_key, $cursor, false);
    aichat_log_debug("ctx $context_id cursor adjusted after dedup", ['cursor'=>$cursor]);
    }

    return [ $items_unique, $unique_total ];
}

/* ==============================
   Worker principal
============================== */
function aichat_process_embeddings_batch( $context_id, $batch_number_ignored ){
    global $wpdb;

    $context_id = (int)$context_id;

    $lock_key  = aichat_lock_key($context_id);
    $start_key = aichat_lock_start_key($context_id);

    // Lock atómico
    $now = time();
    $got = add_option($lock_key,$now,'','no');
    if(!$got){
        $started = (int)get_option($lock_key,0);
        $elapsed = $now - $started;
        if($started && $elapsed <= AICHAT_LOCK_TTL){
            aichat_log_debug("ctx $context_id already locked, skipping", ['elapsed'=>$elapsed]);
            return;
        }
        update_option($lock_key,$now,false);
    aichat_log_debug("ctx $context_id stale lock renewed", ['elapsed'=>$elapsed]);
    }
    update_option($start_key,$now,false);

    try{
        // Cargar contexto
        $context = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}aichat_contexts WHERE id=%d", $context_id),
            ARRAY_A
        );
        if(!$context){ aichat_mark_completed($context_id); return; }

        // Arranque: pending → in_progress (atómico)
        if($context['processing_status']==='pending'){
            $u = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}aichat_contexts
                 SET processing_status='in_progress'
                 WHERE id=%d AND processing_status='pending'", $context_id
            ));
            if($u){ $context['processing_status']='in_progress'; aichat_log_debug("Context $context_id moved to in_progress"); }
        }
        if($context['processing_status']!=='in_progress'){
            aichat_log_debug("Context $context_id skipping (status not in_progress)", ['status'=>$context['processing_status']]);
            return;
        }

        // 1) Quitar duplicados y fijar total
        [ $items, $total_items ] = aichat_normalize_items_and_totals( $context );
        if ( $total_items === 0 ){ aichat_mark_completed($context_id); return; }

        // 2) Leer cursor (índice del siguiente ítem a procesar)
        $cursor_key = aichat_cursor_key($context_id);
        $cursor = (int)get_option($cursor_key, 0);
        if ( $cursor < 0 ) { $cursor = 0; update_option($cursor_key, 0, false); }

        // Si ya hemos llegado al final, completar
        if ( $cursor >= $total_items ){
            $wpdb->update($wpdb->prefix.'aichat_contexts',['processing_progress'=>100],['id'=>$context_id]);
            aichat_mark_completed($context_id);
            return;
        }

        // 3) Tomar el bloque desde el cursor
        $batch_items = array_slice( $items, $cursor, AICHAT_BATCH_SIZE );
        $attempted   = count($batch_items);
        if ( $attempted === 0 ){
            $wpdb->update($wpdb->prefix.'aichat_contexts',['processing_progress'=>100],['id'=>$context_id]);
            aichat_mark_completed($context_id);
            return;
        }

    aichat_log_debug("ctx $context_id processing batch", ['cursor'=>$cursor,'block_size'=>$attempted]);

        $last_post_id = null;

        foreach( $batch_items as $post_id ){
            $last_post_id = $post_id; // guardamos “por qué ID va”
            $post = get_post( $post_id );
            if ( ! $post || $post->post_status !== 'publish' ) {
                aichat_log_debug("Skipping invalid/unpublished post", ['post_id'=>$post_id,'ctx'=>$context_id]);
                continue; // avanzaremos el cursor igualmente
            }

            $text  = function_exists('aichat_build_index_text') ? aichat_build_index_text( $post, $context_id ) : wp_strip_all_tags( $post->post_title . "\n" . $post->post_content );
            $title = $post->post_title;

            if ( $context['context_type'] === 'remoto' && $context['remote_type'] === 'pinecone' ) {
                $provider = aichat_get_context_embedding_provider( $context_id );
                $embedding = aichat_generate_embedding( $text, 'document', $provider );
                if ( ! is_array($embedding) || ! $embedding ) {
                    aichat_log_debug("Failed embedding", ['post_id'=>$post_id,'ctx'=>$context_id]);
                    continue;
                }

                $payload = [
                    'vectors' => [[
                        'id'       => (string)$post_id,
                        'values'   => array_values($embedding),
                        'metadata' => [
                            'post_id'    => (int)$post_id,
                            'title'      => $title,
                            'content'    => mb_substr( $text, 0, 30000 ),
                            'type'       => $post->post_type,
                            'context_id' => (int)$context_id,
                        ],
                    ]],
                    'namespace' => 'aichat_context_'.$context_id,
                ];

                $response = wp_remote_post(
                    rtrim($context['remote_endpoint'],'/').'/vectors/upsert',
                    [
                        'headers'=>[
                            'Api-Key'      => $context['remote_api_key'],
                            'Content-Type' => 'application/json'
                        ],
                        'body'   => wp_json_encode($payload),
                        'timeout'=> 30
                    ]
                );

                if ( is_wp_error($response) ) {
                    aichat_log_debug("HTTP error during upsert", ['ctx'=>$context_id,'post_id'=>$post_id,'error'=>$response->get_error_message()]);
                    continue;
                }
                $code = (int) wp_remote_retrieve_response_code($response);
                $body = json_decode( wp_remote_retrieve_body($response), true );

                if ( !($code >= 200 && $code < 300 && isset($body['upsertedCount']) && (int)$body['upsertedCount'] >= 1) ) {
                    aichat_log_debug("Pinecone upsert failed", ['ctx'=>$context_id,'post_id'=>$post_id,'code'=>$code,'body'=>wp_remote_retrieve_body($response)]);
                    // seguimos avanzando; si quieres reintentos, lo añadimos aparte
                }
            } else {
                $provider = aichat_get_context_embedding_provider( $context_id );
                aichat_index_post( $post_id, $context_id, $provider );
            }
        }

        // 4) Avanzar cursor por el tamaño del bloque intentado (garantiza progreso)
        $new_cursor = min( $cursor + $attempted, $total_items );
        update_option( $cursor_key, $new_cursor, false );
        if ( $last_post_id ) {
            update_option( aichat_last_post_key($context_id), (int)$last_post_id, false );
        }

        // 5) Progreso = cursor/total (monótono por construcción)
        $new_progress = (int) floor( $new_cursor * 100 / max(1,$total_items) );
        $wpdb->update($wpdb->prefix.'aichat_contexts',
            ['processing_progress'=>$new_progress],
            ['id'=>$context_id]
        );
    aichat_log_debug("ctx $context_id progress updated", ['progress'=>$new_progress,'cursor'=>$new_cursor,'total'=>$total_items]);

        // 6) Siguiente batch o fin
        if ( $new_cursor >= $total_items ) {
            aichat_mark_completed($context_id);
        } else {
            // Calculamos el número de batch solo para las args de la acción (el worker usa cursor)
            $next_batch_number = (int) floor( $new_cursor / AICHAT_BATCH_SIZE );
            aichat_schedule_single( time() + 10, 'aichat_process_embeddings_batch', [ $context_id, $next_batch_number ], 'aichat' );
            aichat_log_debug("Scheduled next batch", ['ctx'=>$context_id,'batch_hint'=>$next_batch_number]);
        }

    } catch ( Throwable $e ){
    aichat_log_debug('Fatal in batch processor', ['error'=>$e->getMessage()]);
    } finally {
        delete_option( $lock_key );
        delete_option( $start_key );
    }
}
add_action( 'aichat_process_embeddings_batch', 'aichat_process_embeddings_batch', 10, 2 );

/* ==============================
   Cron de rescate (cada minuto)
   - Dedup primero.
   - Ajusta estado pending → in_progress.
   - Programa en base al CURSOR (no por %).
============================== */
function aichat_cron_process_contexts(){
    global $wpdb;
    aichat_log_debug('Cron scanning contexts', ['ts'=>current_time('mysql')]);

    $rows = $wpdb->get_results(
        "SELECT id, processing_status, processing_progress, items_to_process
         FROM {$wpdb->prefix}aichat_contexts
         WHERE processing_status IN ('pending','in_progress')",
        ARRAY_A
    );
    if ( empty($rows) ){ aichat_log_debug('Cron no pending/in_progress contexts'); return; }

    foreach( $rows as $row ){
        $id       = (int)$row['id'];

        // Dedup + total
        [ $items, $total_items ] = aichat_normalize_items_and_totals( $row );
        if ( $total_items === 0 ){ aichat_mark_completed($id); continue; }

        // Estado
        if ( $row['processing_status'] === 'pending' ) {
            $u = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}aichat_contexts
                 SET processing_status='in_progress'
                 WHERE id=%d AND processing_status='pending'", $id
            ));
            if($u){ aichat_log_debug("Cron ctx $id moved to in_progress"); }
        }

        // Lock vigente → no tocar
        $lock_started = (int) get_option( aichat_lock_key($id), 0 );
        if ( $lock_started && ( time() - $lock_started ) <= AICHAT_LOCK_TTL ) {
            aichat_log_debug("Cron ctx $id locked; skipping");
            continue;
        }

        // Cursor → batch a programar
        $cursor_key = aichat_cursor_key($id);
        $cursor = (int)get_option($cursor_key, 0);
        if ( $cursor >= $total_items ){
            $wpdb->update($wpdb->prefix.'aichat_contexts',['processing_progress'=>100],['id'=>$id]);
            aichat_mark_completed($id);
            continue;
        }

        // Alinear progreso con cursor (evita logs confusos)
        $desired_progress = (int) floor( $cursor * 100 / max(1,$total_items) );
        if ( (int)$row['processing_progress'] !== $desired_progress ) {
            $wpdb->update($wpdb->prefix.'aichat_contexts',
                ['processing_progress'=>$desired_progress],
                ['id'=>$id]
            );
        }

        $batch_number = (int) floor( $cursor / AICHAT_BATCH_SIZE );

        // Si ya hay tarea para ese (ctx,batch), no reprogramar
        $has_wp = wp_next_scheduled('aichat_process_embeddings_batch', [$id,$batch_number]);

        if ( ! $has_wp ) {
            aichat_schedule_single( time(), 'aichat_process_embeddings_batch', [ $id, $batch_number ], 'aichat' );
            aichat_log_debug("Cron scheduled batch", ['ctx'=>$id,'batch'=>$batch_number,'cursor'=>$cursor]);
        } else {
            aichat_log_debug("Cron batch already scheduled; skipping", ['ctx'=>$id,'batch'=>$batch_number]);
        }
    }
}
add_action( 'aichat_cron_process_contexts', 'aichat_cron_process_contexts' );

/**
 * Programar WP-Cron si no está programado
 */
if ( ! wp_next_scheduled( 'aichat_cron_process_contexts' ) ) {
    wp_schedule_event( time(), 'oneminute', 'aichat_cron_process_contexts' );
}

/* ==============================
   AutoSync (hourly) - Esqueleto
   Detecta candidatos y rellena cola (items_to_process) según modo.
   NOTA: Asume contexto recién creado (sin migraciones viejas).
============================== */
add_filter( 'cron_schedules', function( $schedules ) {
    if ( empty( $schedules['aichat_hourly'] ) ) {
    $schedules['aichat_hourly'] = [ 'interval' => HOUR_IN_SECONDS, 'display' => __( 'AIChat Hourly', 'axiachat-ai' ) ];
    }
    return $schedules;
});

if ( ! wp_next_scheduled( 'aichat_autosync_hourly' ) ) {
    wp_schedule_event( time() + 120, 'aichat_hourly', 'aichat_autosync_hourly' );
}

function aichat_autosync_hourly_handler(){
    global $wpdb; 
    $table = $wpdb->prefix.'aichat_contexts';
    // Seleccionar contextos locales con autosync activo (tabla conocida -> concatenación segura del nombre de tabla)
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
    $contexts = $wpdb->get_results(
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
        $wpdb->prepare( "SELECT id, autosync_mode, autosync_post_types FROM $table WHERE context_type=%s AND autosync=%d", 'local', 1 ),
        ARRAY_A
    );
    if ( empty($contexts) ) { aichat_log_debug('AutoSync: no active contexts'); return; }

    foreach( $contexts as $ctx ){
        $context_id = (int)$ctx['id'];
        $mode       = $ctx['autosync_mode'];
        $types_csv  = trim( (string)$ctx['autosync_post_types'] );
        $post_types = [];
        if ( $types_csv !== '' ) {
            foreach( explode(',', $types_csv) as $pt ){ $pt = trim($pt); if($pt!=='') $post_types[] = esc_sql($pt); }
        }
        if ( empty($post_types) ) { $post_types = ['post']; }

        // Construir placeholders dinámicos para IN()
        $placeholders_types = implode(',', array_fill(0, count($post_types), '%s'));
        $base_params = array_merge([$context_id], $post_types);

        // 1. Modificados: posts publicados cuyo post_modified_gmt > max(updated_at/created_at) de su set de chunks
        $modified_sql =
            "SELECT p.ID
             FROM {$wpdb->posts} p
             JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d
             WHERE p.post_status='publish' AND p.post_type IN ($placeholders_types)
             GROUP BY p.ID
             HAVING TIMESTAMP(MAX(COALESCE(c.updated_at,c.created_at))) < TIMESTAMP(MAX(p.post_modified_gmt))
             LIMIT 100";
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders_types is generated from sanitized post types; tables are internal.
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately below via $wpdb->prepare with variadics.
        $modified = $wpdb->get_col( $wpdb->prepare( $modified_sql, ...$base_params ) );

        // 2. Nuevos (solo si updates_and_new): posts sin chunks
        $new = [];
        if ( $mode === 'updates_and_new' ) {
            $new_sql =
                "SELECT p.ID
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->prefix}aichat_chunks c ON c.post_id=p.ID AND c.id_context=%d
                 WHERE c.post_id IS NULL AND p.post_status='publish' AND p.post_type IN ($placeholders_types)
                 ORDER BY p.ID DESC
                 LIMIT 100";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $placeholders_types is generated from sanitized post types; tables are internal.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately below via $wpdb->prepare with variadics.
            $new = $wpdb->get_col( $wpdb->prepare( $new_sql, ...$base_params ) );
        }

        // 3. Huérfanos (deleted/unpublished)
        $orphans = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT c.post_id
             FROM {$wpdb->prefix}aichat_chunks c
             LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_id
             WHERE c.id_context=%d AND (p.ID IS NULL OR p.post_status <> 'publish')
             LIMIT 100",
            $context_id
        ) );

        if ( empty($modified) && empty($new) && empty($orphans) ) {
            aichat_log_debug('AutoSync: no diffs', ['ctx'=>$context_id]);
            continue;
        }

        // Cargar items_to_process actual y añadir nuevos IDs (sin duplicar) – por ahora solo reindex (modify/new). Borrados se gestionarán después.
        // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is a trusted plugin table name via $wpdb->prefix.
        $row = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
            $wpdb->prepare( "SELECT items_to_process FROM $table WHERE id=%d", $context_id ),
            ARRAY_A
        );
        $current = maybe_unserialize( $row['items_to_process'] ?? '' );
        if ( ! is_array($current) ) { $current = []; }

        $queue_ids = $current;
        foreach ( array_merge($modified,$new) as $pid ) { $queue_ids[] = (int)$pid; }
        // Nota: huérfanos los trataremos con borrado directo aquí (sin cola) por simplicidad inicial
        $queue_ids = aichat_stable_unique_ids( $queue_ids );

        $wpdb->update( $table, [ 'items_to_process' => maybe_serialize($queue_ids), 'processing_status' => 'pending' ], [ 'id' => $context_id ] );
        aichat_log_debug('AutoSync queued', ['ctx'=>$context_id,'modified'=>count($modified),'new'=>count($new),'queue_total'=>count($queue_ids),'orphans'=>count($orphans)]);

        // Borrado chunks huérfanos (lote pequeño)
        if ( $orphans ) {
            $orph_placeholders = implode(',', array_fill(0, count($orphans), '%d'));
            $del_params = array_merge([$context_id], array_map('intval',$orphans));
            $delete_sql = "DELETE FROM {$wpdb->prefix}aichat_chunks WHERE id_context=%d AND post_id IN ($orph_placeholders)";
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $orph_placeholders is generated from validated integers; table is internal.
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately below via $wpdb->prepare with variadics.
            $wpdb->query( $wpdb->prepare( $delete_sql, ...$del_params ) );
            aichat_log_debug('AutoSync deleted orphan chunks', ['ctx'=>$context_id,'deleted'=>count($orphans)]);
        }
    }
}
add_action('aichat_autosync_hourly','aichat_autosync_hourly_handler');

/* ==============================
   Cleanup Tool States (hourly)
   Elimina estados de tools expirados (>1 hora)
============================== */
function aichat_cleanup_tool_states() {
    global $wpdb;
    $table = $wpdb->prefix . 'aichat_tool_states';
    
    // Eliminar estados creados hace más de 1 hora
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Scheduled cleanup on internal table.
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- No user input; $table is internal.
    $deleted = $wpdb->query( "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)" );
    
    if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG && $deleted > 0 ) {
        aichat_log_debug( 'Tool states cleanup', [
            'deleted' => $deleted,
            'table' => $table,
        ], true );
    }
}
add_action( 'aichat_cleanup_tool_states', 'aichat_cleanup_tool_states' );

// Programar cleanup si no existe
if ( ! wp_next_scheduled( 'aichat_cleanup_tool_states' ) ) {
    wp_schedule_event( time(), 'hourly', 'aichat_cleanup_tool_states' );
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
