<?php
if ( ! defined('ABSPATH') ) { exit; }
// Moved Macro Tools Layer (from includes/macro-tools.php)

// Macro persistence uses internal plugin tables.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

global $AICHAT_MACRO_TOOLS;
if ( ! is_array( $AICHAT_MACRO_TOOLS ) ) { $AICHAT_MACRO_TOOLS = []; }

/**
 * Persist macro metadata to database
 */
function aichat_persist_macro( $data ) {
  global $wpdb;
  $table = $wpdb->prefix . 'aichat_macros';
  
  if ( empty($data['name']) ) {
    return false;
  }
  
  $defaults = [
    'created_at' => current_time('mysql'),
    'updated_at' => current_time('mysql'),
    'enabled'    => 1,
    'label'      => '',
    'description' => '',
    'source'     => 'local',
    'source_ref' => null,
  ];
  
  $insert = array_merge($defaults, $data);
  
  // Check if exists
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Internal table existence check.
  $exists = $wpdb->get_var(
    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a trusted plugin table name.
    $wpdb->prepare( "SELECT id FROM $table WHERE name = %s", $insert['name'] )
  );
  
  if ( $exists ) {
    // Update
    $insert['updated_at'] = current_time('mysql');
    unset($insert['created_at']); // Don't update created_at
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table update.
    $wpdb->update($table, $insert, ['name' => $insert['name']]);
    return (int) $exists;
  } else {
    // Insert
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table insert.
    $wpdb->insert($table, $insert);
    return (int) $wpdb->insert_id;
  }
}

/**
 * Delete macro by name
 */
function aichat_delete_macro( $name ) {
  global $wpdb;
  $table = $wpdb->prefix . 'aichat_macros';
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table delete.
  return $wpdb->delete($table, ['name' => sanitize_key($name)]);
}

/**
 * Delete macros by source (e.g., when MCP server deleted)
 */
function aichat_delete_macros_by_source( $source, $source_ref = null ) {
  global $wpdb;
  $table = $wpdb->prefix . 'aichat_macros';
  
  if ( $source_ref !== null ) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table delete.
    return $wpdb->delete($table, [
      'source' => sanitize_key($source),
      'source_ref' => sanitize_text_field($source_ref)
    ]);
  } else {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Internal table delete.
    return $wpdb->delete($table, ['source' => sanitize_key($source)]);
  }
}

function aichat_register_macro( array $def ){
  global $AICHAT_MACRO_TOOLS;
  $name = isset($def['name']) ? sanitize_key($def['name']) : '';
  if ( $name === '' ) return false;
  $tools = isset($def['tools']) && is_array($def['tools']) ? array_values(array_filter($def['tools'])) : [];
  if ( empty($tools) ) return false;
  
  $macro = [
    'name'        => $name,
    'label'       => isset($def['label']) ? sanitize_text_field($def['label']) : $name,
    'description' => isset($def['description']) ? sanitize_text_field($def['description']) : '',
    'tools'       => $tools,
    'source'      => isset($def['source']) ? sanitize_key($def['source']) : 'local',
    'source_ref'  => isset($def['source_ref']) ? sanitize_text_field($def['source_ref']) : null,
  ];
  
  // Register in memory
  $AICHAT_MACRO_TOOLS[$name] = $macro;
  
  // Persist to database
  aichat_persist_macro([
    'name'        => $macro['name'],
    'label'       => $macro['label'],
    'description' => $macro['description'],
    'source'      => $macro['source'],
    'source_ref'  => $macro['source_ref'],
    'tools_json'  => wp_json_encode($macro['tools']),
  ]);
  
  return true;
}

function aichat_get_registered_macros(){
  global $AICHAT_MACRO_TOOLS, $wpdb;
  
  // Load from database (primary source of truth)
  $table = $wpdb->prefix . 'aichat_macros';
  
  // Check if table exists first (for fresh installs)
  $table_exists = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s",
    $table
  ));
  
  if ( $table_exists ) {
    // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name and parameters are safe.
    $rows = $wpdb->get_results(
      // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin/runtime read of internal table.
      // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- No user input; $table is internal.
      "SELECT * FROM $table WHERE enabled = 1 ORDER BY label ASC",
      ARRAY_A
    );
    
    if ( $rows ) {
      // Clear and rebuild from database
      $AICHAT_MACRO_TOOLS = [];
      
      foreach ( $rows as $row ) {
        $tools = json_decode($row['tools_json'], true);
        if ( ! is_array($tools) ) {
          $tools = [];
        }
        
        $AICHAT_MACRO_TOOLS[$row['name']] = [
          'name'        => $row['name'],
          'label'       => $row['label'],
          'description' => $row['description'],
          'tools'       => $tools,
          'source'      => $row['source'],
          'source_ref'  => $row['source_ref'] ?? null,
        ];
      }
    }
  }
  
  // Return what we have (from DB or from memory if DB not available)
  return $AICHAT_MACRO_TOOLS;
}

function aichat_expand_macros_to_atomic( array $selected_ids ){
  if ( empty($selected_ids) ) return [];
  $macros = aichat_get_registered_macros(); $out = [];
  foreach($selected_ids as $id){
    if ( isset($macros[$id]) ) { foreach($macros[$id]['tools'] as $t){ $out[]=$t; } }
    else { $out[] = $id; }
  }
  $uniq = []; foreach($out as $t){ if(!isset($uniq[$t])) $uniq[$t]=true; } return array_keys($uniq);
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
