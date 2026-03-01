<?php

/**
 * Leads Admin AJAX Handlers
 *
 * @package AIChat
 * @subpackage Leads
 */

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Save settings via AJAX
 */
add_action('wp_ajax_aichat_leads_save_settings', 'aichat_leads_ajax_save_settings');
function aichat_leads_ajax_save_settings()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	// Validate destination
	$valid_destinations = ['internal', 'google_sheets', 'cpt', 'cf7', 'wpforms'];
	$destination = isset($_POST['destination']) ? sanitize_key($_POST['destination']) : 'internal';
	if (! in_array($destination, $valid_destinations, true)) {
		$destination = 'internal';
	}

	$settings = [
		'enabled' => ! empty($_POST['enabled']),
		'destination' => $destination,
		'store_ip' => ! empty($_POST['store_ip']),
		'retention_days' => isset($_POST['retention_days']) ? absint($_POST['retention_days']) : 0,
		'notify_enabled' => ! empty($_POST['notify_enabled']),
		'notify_email' => isset($_POST['notify_email']) ? sanitize_email(wp_unslash($_POST['notify_email'])) : '',
		'cf7_form_id' => isset($_POST['cf7_form_id']) ? absint($_POST['cf7_form_id']) : 0,
		'wpforms_form_id' => isset($_POST['wpforms_form_id']) ? absint($_POST['wpforms_form_id']) : 0,
	];

	AIChat_Leads_Manager::update_settings($settings);

	// Save Google Sheets settings separately
	if (isset($_POST['gsheets_spreadsheet_id'])) {
		update_option('aichat_leads_gsheets_spreadsheet_id', sanitize_text_field(wp_unslash($_POST['gsheets_spreadsheet_id'])));
	}
	if (isset($_POST['gsheets_sheet_name'])) {
		update_option('aichat_leads_gsheets_sheet_name', sanitize_text_field(wp_unslash($_POST['gsheets_sheet_name'])));
	}

	// Save Webhook URL
	if (isset($_POST['webhook_url'])) {
		update_option('aichat_leads_webhook_url', esc_url_raw(wp_unslash($_POST['webhook_url'])));
	}

	wp_send_json_success(['message' => __('Settings saved successfully.', 'axiachat-ai')]);
}

/**
 * Save email template via AJAX
 */
add_action('wp_ajax_aichat_leads_save_email_template', 'aichat_leads_ajax_save_email_template');
function aichat_leads_ajax_save_email_template()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$email_subject = isset($_POST['email_subject']) ? sanitize_text_field(wp_unslash($_POST['email_subject'])) : '';
	$email_body = isset($_POST['email_body']) ? sanitize_textarea_field(wp_unslash($_POST['email_body'])) : '';

	$settings = AIChat_Leads_Manager::get_settings();
	$settings['email_subject'] = $email_subject;
	$settings['email_body'] = $email_body;

	AIChat_Leads_Manager::update_settings($settings);

	wp_send_json_success(['message' => __('Email template saved successfully.', 'axiachat-ai')]);
}

/**
 * Get lead detail via AJAX
 */
add_action('wp_ajax_aichat_leads_get_lead', 'aichat_leads_ajax_get_lead');
function aichat_leads_ajax_get_lead()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
	if (! $id) {
		wp_send_json_error(['message' => 'Invalid lead ID']);
	}

	$lead = AIChat_Leads_Manager::get_lead($id);
	if (! $lead) {
		wp_send_json_error(['message' => 'Lead not found']);
	}

	// Parse campos_extra if JSON
	if (! empty($lead['campos_extra'])) {
		$lead['campos_extra'] = json_decode($lead['campos_extra'], true);
	}

	wp_send_json_success(['lead' => $lead]);
}

/**
 * Update lead status via AJAX
 */
add_action('wp_ajax_aichat_leads_update_status', 'aichat_leads_ajax_update_status');
function aichat_leads_ajax_update_status()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
	$status = isset($_POST['status']) ? sanitize_key($_POST['status']) : '';

	if (! $id || ! $status) {
		wp_send_json_error(['message' => 'Invalid parameters']);
	}

	$result = AIChat_Leads_Manager::update_status($id, $status);

	if ($result !== false) {
		wp_send_json_success(['message' => __('Status updated.', 'axiachat-ai')]);
	}

	wp_send_json_error(['message' => 'Update failed']);
}

/**
 * Delete lead via AJAX
 */
add_action('wp_ajax_aichat_leads_delete', 'aichat_leads_ajax_delete');
function aichat_leads_ajax_delete()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$id = isset($_POST['id']) ? absint($_POST['id']) : 0;

	if (! $id) {
		wp_send_json_error(['message' => 'Invalid lead ID']);
	}

	$result = AIChat_Leads_Manager::delete($id);

	if ($result) {
		wp_send_json_success(['message' => __('Lead deleted.', 'axiachat-ai')]);
	}

	wp_send_json_error(['message' => 'Delete failed']);
}

/**
 * Bulk delete leads via AJAX
 */
add_action('wp_ajax_aichat_leads_bulk_delete', 'aichat_leads_ajax_bulk_delete');
function aichat_leads_ajax_bulk_delete()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$ids = isset($_POST['ids']) ? array_map('absint', (array) $_POST['ids']) : [];

	if (empty($ids)) {
		wp_send_json_error(['message' => 'No leads selected']);
	}

	$deleted = AIChat_Leads_Manager::bulk_delete($ids);

	wp_send_json_success([
		'message' => sprintf(
			/* translators: %d: number of deleted leads */
			__('%d leads deleted.', 'axiachat-ai'),
			$deleted
		),
	]);
}

/**
 * Export leads to CSV via AJAX
 */
add_action('wp_ajax_aichat_leads_export', 'aichat_leads_ajax_export');
function aichat_leads_ajax_export()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_die('Permission denied');
	}

	$args = [];

	if (! empty($_GET['estado'])) {
		$args['estado'] = sanitize_key($_GET['estado']);
	}

	if (! empty($_GET['s'])) {
		$args['search'] = sanitize_text_field(wp_unslash($_GET['s']));
	}

	$csv = AIChat_Leads_Manager::export_csv($args);

	$filename = 'leads-export-' . gmdate('Y-m-d-His') . '.csv';

	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=' . $filename);
	header('Pragma: no-cache');
	header('Expires: 0');

	// Add BOM for Excel UTF-8 compatibility
	echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BOM bytes for UTF-8 CSV.
	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw CSV binary download, Content-Type: text/csv, not HTML context.
	echo $csv;
	exit;
}

/**
 * Get CF7 form fields via AJAX
 */
add_action('wp_ajax_aichat_leads_get_cf7_fields', 'aichat_leads_ajax_get_cf7_fields');
function aichat_leads_ajax_get_cf7_fields()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;

	if (! $form_id || ! class_exists('AIChat_Leads_Adapter_CF7')) {
		wp_send_json_error(['message' => 'Invalid form']);
	}

	$fields = AIChat_Leads_Adapter_CF7::get_form_fields($form_id);

	wp_send_json_success(['fields' => $fields]);
}

/**
 * Get WPForms form fields via AJAX
 */
add_action('wp_ajax_aichat_leads_get_wpforms_fields', 'aichat_leads_ajax_get_wpforms_fields');
function aichat_leads_ajax_get_wpforms_fields()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$form_id = isset($_POST['form_id']) ? absint($_POST['form_id']) : 0;

	if (! $form_id || ! class_exists('AIChat_Leads_Adapter_WPForms')) {
		wp_send_json_error(['message' => 'Invalid form']);
	}

	$fields = AIChat_Leads_Adapter_WPForms::get_form_fields($form_id);

	wp_send_json_success(['fields' => $fields]);
}
/**
 * Google Sheets: Disconnect
 */
add_action('wp_ajax_aichat_leads_gsheets_disconnect', 'aichat_leads_ajax_gsheets_disconnect');
function aichat_leads_ajax_gsheets_disconnect()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	AIChat_Leads_GSheets_OAuth::disconnect();

	// Also clear spreadsheet settings
	delete_option('aichat_leads_gsheets_spreadsheet_id');
	delete_option('aichat_leads_gsheets_sheet_name');

	wp_send_json_success(['message' => __('Disconnected from Google Sheets.', 'axiachat-ai')]);
}

/**
 * Google Sheets: Test connection
 */
add_action('wp_ajax_aichat_leads_gsheets_test', 'aichat_leads_ajax_gsheets_test');
function aichat_leads_ajax_gsheets_test()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$spreadsheet_id = isset($_POST['spreadsheet_id']) ? sanitize_text_field(wp_unslash($_POST['spreadsheet_id'])) : '';

	if (empty($spreadsheet_id)) {
		wp_send_json_error(['message' => __('Please enter a spreadsheet ID.', 'axiachat-ai')]);
	}

	$result = AIChat_Leads_GSheets_Client::test_connection($spreadsheet_id);

	if (is_wp_error($result)) {
		wp_send_json_error(['message' => $result->get_error_message()]);
	}

	wp_send_json_success([
		'message' => sprintf(
			/* translators: %s: Spreadsheet title */
			__('Connected to: %s', 'axiachat-ai'),
			$result['title']
		),
		'title' => $result['title'],
		'sheets' => $result['sheets'],
	]);
}

/**
 * Google Sheets: Save spreadsheet settings
 */
add_action('wp_ajax_aichat_leads_gsheets_save', 'aichat_leads_ajax_gsheets_save');
function aichat_leads_ajax_gsheets_save()
{
	check_ajax_referer('aichat_leads_admin', 'nonce');

	if (! current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Permission denied']);
	}

	$spreadsheet_id = isset($_POST['spreadsheet_id']) ? sanitize_text_field(wp_unslash($_POST['spreadsheet_id'])) : '';
	$sheet_name = isset($_POST['sheet_name']) ? sanitize_text_field(wp_unslash($_POST['sheet_name'])) : 'Sheet1';

	update_option('aichat_leads_gsheets_spreadsheet_id', $spreadsheet_id);
	update_option('aichat_leads_gsheets_sheet_name', $sheet_name);

	wp_send_json_success(['message' => __('Google Sheets settings saved.', 'axiachat-ai')]);
}
