<?php

// SSA Helpers (prefixed with aichat_)
if (! defined('ABSPATH')) {
	exit;
}

/**
 * aichat_ssa_is_active: Check if Simply Schedule Appointments is available
 */
function aichat_ssa_is_active()
{
	return function_exists('ssa') || class_exists('SSA_Appointment') || class_exists('SSA_Appointment_Model');
}

/**
 * aichat_ssa_get_services: Return bookable appointment types (id, title/name, duration in minutes)
 */
function aichat_ssa_get_services()
{
	if (! aichat_ssa_is_active()) {
		return [];
	}
	try {
		$rows = [];
		if (function_exists('ssa')) {
			$ssa = call_user_func('ssa');
			if (isset($ssa->appointment_type_model) && method_exists($ssa->appointment_type_model, 'get_all_appointment_types')) {
				$rows = $ssa->appointment_type_model->get_all_appointment_types();
			} elseif (isset($ssa->appointment_type_model) && method_exists($ssa->appointment_type_model, 'get_all')) {
				// Fallback to generic list if provided by SSA version
				$rows = $ssa->appointment_type_model->get_all();
			}
		}
		$out = [];
		foreach ((array) $rows as $r) {
			$id = isset($r['id']) ? (int) $r['id'] : 0;
			if ($id <= 0) {
				continue;
			}
			$title = isset($r['title']) ? (string) $r['title'] : (isset($r['name']) ? (string) $r['name'] : '');
			$duration_min = isset($r['duration']) ? (int) $r['duration'] : 0; // SSA returns minutes
			$out[] = [
				'id' => $id,
				'title' => $title,
				// Keep legacy 'name' key for compatibility with callers expecting it
				'name' => $title,
				// duration in minutes
				'duration' => $duration_min,
				// optionally pass slug/status if available
				'slug' => isset($r['slug']) ? (string) $r['slug'] : '',
				'status' => isset($r['status']) ? (string) $r['status'] : '',
			];
		}

		return $out;
	} catch (Exception $e) {
		return [];
	}
}

/**
 * aichat_ssa_get_upcoming_slots
 * Get upcoming availability slots for an appointment type in a human-readable format.
 * Inputs: from/to in 'Y-m-d H:i:s' (site local timezone). If only 'Y-m-d' is provided, we normalize to day start/end.
 * Output: array of slots with 'start' and 'end' as 'Y-m-d H:i:s' in the appointment type's local timezone.
 */
function aichat_ssa_get_upcoming_slots($appointment_type_id, $from = null, $to = null, $starts_only = false)
{
	if (! aichat_ssa_is_active()) {
		return [];
	}
	$appointment_type_id = (int) $appointment_type_id;
	if ($appointment_type_id <= 0) {
		return [];
	}
	try {
		if (empty($from)) {
			$from = current_time('mysql');
		} // WP local 'Y-m-d H:i:s'
		if (empty($to)) {
			// add 7 days
			$tmp = new DateTimeImmutable($from, wp_timezone());
			$to = $tmp->add(new DateInterval('P7D'))->format('Y-m-d H:i:s');
		}
		// Normalize date-only inputs
		if (is_string($from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
			$from .= ' 00:00:00';
		}
		if (is_string($to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
			$to .= ' 23:59:59';
		}

		if (! function_exists('ssa')) {
			return [];
		}
		$ssa = call_user_func('ssa');
		if (! isset($ssa->availability_agent) || ! $ssa->availability_agent) {
			return [];
		}

		// Get appointment-type local timezone (business timezone)
		try {
			$local_tz = $ssa->utils->get_datetimezone($appointment_type_id);
		} catch (\Throwable $e) {
			$local_tz = wp_timezone();
		}

		// Convert local range to UTC strings expected by agent (without relying on SSA_Utils)
		$from_dt = new DateTimeImmutable($from, $local_tz);
		$to_dt = new DateTimeImmutable($to, $local_tz);
		$from_utc = $from_dt->setTimezone(new DateTimeZone('UTC'));
		$to_utc = $to_dt->setTimezone(new DateTimeZone('UTC'));
		$start_str = $from_utc->format('Y-m-d H:i:s');
		$end_str = $to_utc->format('Y-m-d H:i:s');

		// Fetch bookable local start strings from agent
		$starts = (array) $ssa->availability_agent->disponibilidad($appointment_type_id, $start_str, $end_str);
		if (empty($starts)) {
			return [];
		}

		// Optional compact mode: return only start datetimes to save tokens
		if (!empty($starts_only)) {
			// Ensure all are strings; sanitize shape defensively
			return array_values(array_filter(array_map(function ($s) {
				return is_string($s) ? $s : null;
			}, $starts)));
		}

		// Determine duration in minutes for this appointment type
		$duration_minutes = 0;
		foreach (aichat_ssa_get_services() as $svc) {
			if ((int) $svc['id'] === $appointment_type_id) {
				$duration_minutes = isset($svc['duration']) ? (int) $svc['duration'] : 0;
				break;
			}
		}
		if ($duration_minutes <= 0) {
			$duration_minutes = 30;
		}

		// Build slots with start/end as local strings
		$slots = [];
		foreach ($starts as $start_string) {
			if (!is_string($start_string) || $start_string === '') {
				continue;
			}
			try {
				$start_local = new DateTimeImmutable($start_string, $local_tz);
				$end_local = $start_local->add(new DateInterval('PT' . ($duration_minutes * 60) . 'S'));
				$slots[] = [
					'start' => $start_local->format('Y-m-d H:i:s'),
					'end' => $end_local->format('Y-m-d H:i:s'),
				];
			} catch (\Throwable $e) {
				// On parse error, just echo the start and estimate end by string
				$slots[] = ['start' => $start_string, 'end' => $start_string];
			}
		}

		return $slots;
	} catch (Exception $e) {
		return [];
	}
}

/**
 * aichat_ssa_create_appointment: Create an appointment
 */
function aichat_ssa_create_appointment($service_id, $customer, $start_ts)
{
	if (! aichat_ssa_is_active()) {
		return ['ok' => false, 'error' => 'ssa_not_active'];
	}
	$service_id = (int) $service_id;
	$start_ts = (int) $start_ts;
	$customer = is_array($customer) ? $customer : [];
	$name = isset($customer['name']) ? sanitize_text_field($customer['name']) : '';
	$email = isset($customer['email']) ? sanitize_email($customer['email']) : '';
	$phone = isset($customer['phone']) ? sanitize_text_field($customer['phone']) : '';
	if ($service_id <= 0 || $start_ts <= 0 || !is_email($email)) {
		return ['ok' => false, 'error' => 'invalid_input'];
	}
	try {
		if (function_exists('ssa')) {
			$ssa = call_user_func('ssa');
			$app = isset($ssa->appointment_model) ? $ssa->appointment_model->create([
				'service_id' => $service_id,
				'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone],
				'start_date' => gmdate('Y-m-d H:i:s', $start_ts),
			]) : false;
		} else {
			$modelClass = 'SSA_Appointment_Model';
			if (class_exists($modelClass)) {
				$model = new $modelClass();
				$app = $model->create([
					'service_id' => $service_id,
					'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone],
					'start_date' => gmdate('Y-m-d H:i:s', $start_ts),
				]);
			} else {
				$app = false;
			}
		}
		if (empty($app) || !is_array($app)) {
			return ['ok' => false, 'error' => 'create_failed'];
		}

		return ['ok' => true, 'appointment_id' => (int) ($app['id'] ?? 0)];
	} catch (Exception $e) {
		return ['ok' => false, 'error' => 'exception'];
	}
}
