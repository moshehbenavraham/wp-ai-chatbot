<?php
if ( ! defined('ABSPATH') ) { exit; }

// Admin-only logs page; direct DB reads are expected here.
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

/**
 * Standalone logs page (for direct access via URL)
 */
function aichat_tools_logs_page(){
  echo '<div class="wrap"><h1>'.esc_html__('AI Tools Logs','axiachat-ai').'</h1>';
  aichat_tools_logs_content();
  echo '</div>';
}

/**
 * Logs content (embeddable in tabs or standalone)
 */
function aichat_tools_logs_content(){
  global $wpdb; $table = $wpdb->prefix.'aichat_tool_calls';
  
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only internal table existence check.
  $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=%s", $table));
  if ( ! $exists ) { 
    echo '<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>'.esc_html__('Tool calls table does not exist yet. Logs will appear after the first tool execution.','axiachat-ai').'</div>'; 
    return; 
  }
  
  // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin-only internal table listing.
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name comes from $wpdb->prefix; no user input in query
  $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id DESC LIMIT 500");
  
  if(!$rows){ 
    echo '<div class="alert alert-secondary"><i class="bi bi-inbox me-2"></i>'.esc_html__('No tool calls recorded yet. Logs will appear here when bots execute tools.','axiachat-ai').'</div>'; 
    return; 
  }
  
  /* translators: %d: Number of tool executions shown */
  echo '<p class="text-muted mb-3"><i class="bi bi-clock-history me-1"></i>'.sprintf(esc_html__('Showing last %d tool executions','axiachat-ai'), count($rows)).'</p>';
  
  echo '<div class="table-responsive">';
  echo '<table class="table table-striped table-hover table-sm"><thead class="table-light"><tr>'
    .'<th style="width:60px;">ID</th>'
    .'<th>'.esc_html__('Bot','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Tool','axiachat-ai').'</th>'
    .'<th style="width:60px;">'.esc_html__('Round','axiachat-ai').'</th>'
    .'<th style="width:80px;">'.esc_html__('Duration','axiachat-ai').'</th>'
    .'<th style="width:150px;">'.esc_html__('Date','axiachat-ai').'</th>'
    .'<th>'.esc_html__('Output Excerpt','axiachat-ai').'</th>'
    .'</tr></thead><tbody>';
  foreach($rows as $r){
    $excerpt = mb_substr((string)$r->output_excerpt,0,100);
    $duration = intval($r->duration_ms);
    $duration_class = $duration > 5000 ? 'text-danger' : ($duration > 2000 ? 'text-warning' : 'text-success');
    echo '<tr>'
      .'<td><small class="text-muted">'.intval($r->id).'</small></td>'
      .'<td><span class="badge bg-primary">'.esc_html($r->bot_slug).'</span></td>'
      .'<td><code>'.esc_html($r->tool_name).'</code></td>'
      .'<td class="text-center">'.intval($r->round).'</td>'
      .'<td class="'.esc_attr($duration_class).'">'.intval($r->duration_ms).' ms</td>'
      .'<td><small>'.esc_html($r->created_at).'</small></td>'
      .'<td><code class="small" style="word-break:break-all;">'.esc_html($excerpt).( strlen((string)$r->output_excerpt) > 100 ? '...' : '' ).'</code></td>'
      .'</tr>';
  }
  echo '</tbody></table>';
  echo '</div>';
}

// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
