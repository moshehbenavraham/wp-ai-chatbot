<?php

// SSA Tools and Macros registration
//
// Notas sobre convenciones de registro de tools (importante para mantener claridad):
// - 'description': TEXTO PARA EL MODELO (LLM). Úsalo para explicar CUÁNDO/COMO debe usar la herramienta.
//   Puedes incluir reglas tipo "MUST/SHOULD/NEVER". Este texto NO lo ve el usuario final.
// - 'activity_label': TEXTO PARA LA UI (barra de progreso/estado en Test Tools y/o frontend). El modelo no lo ve.
// - 'name': nombre expuesto al modelo para invocar la tool (function name). Mantener snake_case estable.
// - 'schema': JSON Schema que el modelo usa para construir los argumentos. Las 'descriptions' de cada propiedad
//   también las "lee" el modelo; sé concreto (formatos de fecha, unidades, restricciones). Usar additionalProperties=false.
// - 'timeout' / 'parallel' / 'max_calls': límites de ejecución del servidor (no del modelo); NO afectan al razonamiento del modelo.
// - 'callback': implementación PHP; nunca expongas detalles internos sensibles en su salida.
// - 'strict' (opcional): si está presente y true, fuerza validación estricta de parámetros en algunos proveedores.
if (! defined('ABSPATH')) {
	exit;
}

require_once __DIR__ . '/helpers.php';

if (! function_exists('aichat_register_tool_safe')) {
	if (function_exists('aichat_register_tool')) {
		function aichat_register_tool_safe($id, $args)
		{
			return aichat_register_tool($id, $args);
		}
	} else {
		if (function_exists('aichat_log_debug')) {
			aichat_log_debug('SSA tools: AI Tools API missing (aichat_register_tool)');
		}

		return;
	}
}
if (function_exists('aichat_log_debug')) {
	aichat_log_debug('SSA tools: starting registration');
}

// Tool: List SSA appointment types
aichat_register_tool_safe('aichat_ssa_list_services', [
  'type' => 'function',
  'name' => 'aichat_ssa_list_services',
  // description (MODEL-facing): generic so it works with any Agenda plugin.
  'description' => 'List the Agenda appointment types (id, title, duration in minutes).',
  // activity_label (para la UI): texto breve de estado mientras corre la tool.
  'activity_label' => 'Listing appointment types...',
  // properties must be a JSON object, not an array
  'schema' => ['type' => 'object', 'properties' => (object) [], 'required' => [], 'additionalProperties' => false],
  'callback' => function () {
  	if (! aichat_ssa_is_active()) {
  		return ['ok' => false, 'error' => 'ssa_not_active'];
  	}
  	$services = aichat_ssa_get_services();

  	return ['ok' => true, 'services' => $services, 'total' => count($services)];
  },
  'timeout' => 6,
  'parallel' => true,
  'max_calls' => 2,
]);
if (function_exists('aichat_log_debug')) {
	aichat_log_debug('SSA tools: registered aichat_ssa_list_services');
}

// Tool: Get upcoming availability slots for an appointment type
aichat_register_tool_safe('aichat_ssa_get_availability', [
  'type' => 'function',
  'name' => 'aichat_ssa_get_availability',
  // description (MODEL-facing): generic (Agenda). Mentions time format and optionality.
  'description' => 'Query available Agenda time slots for an appointment type. Optional date range (Y-m-d H:i:s, site local time). If no type is provided, use the default.',
  'activity_label' => 'Fetching appointment availability...',
  'schema' => [
	'type' => 'object',
	'properties' => [
  // Property descriptions are read by the MODEL to build correct arguments.
  'appointment_type_id' => ['type' => 'integer', 'description' => 'Agenda appointment type ID (optional; defaults to 1).', 'default' => 1],
  'from' => ['type' => 'string', 'description' => 'Range start (Y-m-d H:i:s) in site local time; Y-m-d accepted'],
  'to' => ['type' => 'string', 'description' => 'Range end (Y-m-d H:i:s) in site local time; Y-m-d accepted'],
  'starts_only' => ['type' => 'boolean', 'description' => 'If true, return only start datetimes (Y-m-d H:i:s) instead of full start/end slots'],
	],
	'required' => [],
	'additionalProperties' => false,
  ],
  'callback' => function ($args) {
  	$sid = isset($args['appointment_type_id']) ? (int) $args['appointment_type_id'] : 1;
  	if ($sid <= 0) {
  		$sid = 1;
  	}
  	$from = isset($args['from']) ? (string) $args['from'] : null;
  	$to = isset($args['to']) ? (string) $args['to'] : null;
  	$starts_only = !empty($args['starts_only']);
  	$slots = aichat_ssa_get_upcoming_slots($sid, $from, $to, $starts_only);
  	// Normalize response keys depending on mode
  	if ($starts_only) {
  		return ['ok' => true, 'appointment_type_id' => $sid, 'starts' => $slots, 'count' => count($slots), 'compact' => true];
  	}

  	return ['ok' => true, 'appointment_type_id' => $sid, 'slots' => $slots, 'count' => count($slots), 'compact' => false];
  },
  'timeout' => 8,
  'parallel' => true,
  'max_calls' => 2,
]);
if (function_exists('aichat_log_debug')) {
	aichat_log_debug('SSA tools: registered aichat_ssa_get_availability');
}

// Tool: Create appointment (using booking agent)
// NOTE: This should generally be guarded by server-side policy/macros and possibly captcha/confirmation flows.
aichat_register_tool_safe('aichat_ssa_create_appointment', [
  'type' => 'function',
  'name' => 'aichat_ssa_create_appointment',
  // description (MODEL-facing): generic (Agenda). appointment_type_id optional with default value.
  'description' => 'Create an appointment in the Agenda. Requires start datetime and customer info. The appointment type is optional (defaults to 1).',
  'activity_label' => 'Creating appointment...',
  'schema' => [
	'type' => 'object',
	'properties' => [
  // Property descriptions guide the MODEL; keep names/formats exact.
  'appointment_type_id' => ['type' => 'integer', 'description' => 'Agenda appointment type ID (optional; defaults to 1).', 'default' => 1],
  'start' => ['type' => 'string', 'description' => 'Selected slot start (Y-m-d H:i:s) in site local time; Y-m-d accepted'],
  'customer' => ['type' => 'object', 'description' => 'Customer info', 'properties' => [
		'name' => ['type' => 'string'],
		'email' => ['type' => 'string'],
		'phone' => ['type' => 'string'],
	  ], 'required' => ['email'], 'additionalProperties' => false],
	],
	'required' => ['start', 'customer'],
	'additionalProperties' => false,
  ],
  'callback' => function ($args, $ctx = []) {
  	$sid = isset($args['appointment_type_id']) ? (int) $args['appointment_type_id'] : 1;
  	if ($sid <= 0) {
  		$sid = 1;
  	}
  	$start = isset($args['start']) ? (string) $args['start'] : '';
  	$cust = isset($args['customer']) && is_array($args['customer']) ? $args['customer'] : [];
  	// Route via booking agent helper so it handles timezone and availability validations
  	if (! function_exists('aichat_ssa_reservar')) {
  		// Fallback to legacy helper if booking agent not present (convert start to timestamp UTC if needed)
  		$start_ts = strtotime($start);
  		$res = aichat_ssa_create_appointment($sid, $cust, $start_ts ?: 0);
  	} else {
  		$res = aichat_ssa_reservar($sid, $start, 'pending_form', $cust, ['input_timezone' => 'local']);
  	}

  	return $res;
  },
  'timeout' => 10,
  'parallel' => false,
  'max_calls' => 1,
]);
if (function_exists('aichat_log_debug')) {
	aichat_log_debug('SSA tools: registered aichat_ssa_create_appointment');
}

// Macros for SSA flows
if (function_exists('aichat_register_macro')) {
	// Macros: agrupan tools para facilitar su selección en bots. Las descriptions/labels de macros son para ADMIN/UI.
	// El MODELO no "ve" las macros; ve las tools resultantes activadas en el bot.
	aichat_register_macro([
	'name' => 'ssa_availability',
	'label' => 'SSA: Availability',
	'description' => 'Enable assistant to list appointment types and fetch availability slots for a selected type.', // UI/Admin
	'tools' => ['aichat_ssa_list_services', 'aichat_ssa_get_availability'],
	'source' => 'plugin',
	'source_ref' => 'simply-schedule-appointments',
	]);
	aichat_register_macro([
	'name' => 'ssa_booking',
	'label' => 'SSA: Booking',
	'description' => 'Enable assistant to create appointments after the user confirms a slot.', // UI/Admin
	'tools' => ['aichat_ssa_create_appointment'],
	'source' => 'plugin',
	'source_ref' => 'simply-schedule-appointments',
	]);
	if (function_exists('aichat_log_debug')) {
		aichat_log_debug('SSA tools: macros registered');
	}
} else {
	if (function_exists('aichat_log_debug')) {
		aichat_log_debug('SSA tools: macro API missing (aichat_register_macro)');
	}
}

// === System instructions functions registry: add a safe SSA availability summary ===
if (function_exists('add_filter')) {
	add_filter('aichat_system_functions_registry', function ($reg) {
		if (!is_array($reg)) {
			$reg = [];
		}
		if (function_exists('aichat_ssa_is_active') && function_exists('aichat_ssa_get_upcoming_slots') && aichat_ssa_is_active()) {
			$reg['ssa_availability'] = [
			  'callback' => 'aichat_sysfn_ssa_availability',
			  'schema' => [
				'appointment_type_id' => ['type' => 'integer', 'min' => 1, 'default' => 1],
				'from' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$', 'default' => ''],
				'to' => ['type' => 'string', 'pattern' => '^\d{4}-\d{2}-\d{2}(\s\d{2}:\d{2}:\d{2})?$', 'default' => ''],
				'starts_only' => ['type' => 'boolean', 'default' => true],
				// Optional shortcut: when provided and both from/to are empty, use [now, now + days] range in site local time
				'days' => ['type' => 'integer', 'min' => 1, 'max' => 60, 'default' => 0],
				// Allow larger lists when needed (capped to keep system prompt reasonable)
				'limit' => ['type' => 'integer', 'min' => 1, 'max' => 200, 'default' => 3],
			  ],
			  // Increase default max length so long lists aren't truncated when embedded in System instructions.
			  // Allow overriding via filter if needed.
			  'max_len' => apply_filters('aichat_sysfn_ssa_availability_max_len', 4000),
			];
		}

		return $reg;
	});

	if (! function_exists('aichat_sysfn_ssa_availability')) {
		function aichat_sysfn_ssa_availability($args)
		{
			$sid = isset($args['appointment_type_id']) ? (int) $args['appointment_type_id'] : 1;
			if ($sid <= 0) {
				$sid = 1;
			}
			$from = isset($args['from']) && is_string($args['from']) ? trim($args['from']) : '';
			$to = isset($args['to']) && is_string($args['to']) ? trim($args['to']) : '';
			$starts_only = !empty($args['starts_only']);
			$days = isset($args['days']) ? (int) $args['days'] : 0;
			// If days > 0 and no explicit from/to provided, compute a local-time range [now, now + days]
			if ($days > 0 && $from === '' && $to === '') {
				// Prefer wp_date with site timezone; fallback to date_i18n with local timestamps
				if (function_exists('wp_date')) {
					try {
						$tz = function_exists('wp_timezone') ? wp_timezone() : null;
					} catch (\Throwable $e) {
						$tz = null;
					}
					$now_gmt = function_exists('current_time') ? current_time('timestamp', true) : time();
					$from = wp_date('Y-m-d H:i:s', $now_gmt, $tz ?: null);
					$to = wp_date('Y-m-d H:i:s', $now_gmt + ($days * DAY_IN_SECONDS), $tz ?: null);
				} else {
					// Legacy fallback
					$now_local = function_exists('current_time') ? current_time('timestamp') : time();
					$from = date_i18n('Y-m-d H:i:s', $now_local, false);
					$to = date_i18n('Y-m-d H:i:s', $now_local + ($days * DAY_IN_SECONDS), false);
				}
			}
			$limit = isset($args['limit']) ? max(1, (int) $args['limit']) : 3;
			try {
				$slots = aichat_ssa_get_upcoming_slots($sid, $from ?: null, $to ?: null, $starts_only);
				if (!is_array($slots)) {
					return '';
				}
				if ($starts_only) {
					$list = array_slice(array_map(function ($s) {
						return is_string($s) ? $s : (is_array($s) && isset($s['start']) ? $s['start'] : '');
					}, $slots), 0, $limit);
					$list = array_values(array_filter($list, function ($s) {
						return $s !== '';
					}));
					if (empty($list)) {
						return 'No upcoming starts available.';
					}

					return 'Next starts: ' . implode(', ', array_map('sanitize_text_field', $list)) . ' (total: ' . count($slots) . ')';
				}
				// full slots with start/end
				$list = [];
				foreach ($slots as $slot) {
					if (is_array($slot) && isset($slot['start'],$slot['end'])) {
						$list[] = sanitize_text_field($slot['start']) . '–' . sanitize_text_field($slot['end']);
						if (count($list) >= $limit) {
							break;
						}
					}
				}
				if (empty($list)) {
					return 'No upcoming slots available.';
				}

				return 'Next slots: ' . implode(', ', $list) . ' (total: ' . count($slots) . ')';
			} catch (\Throwable $e) {
				return '';
			}
		}
	}
}
