<?php

if (! defined('ABSPATH')) {
	exit;
}

/**
 * Simple Booking/Reservation Agent.
 *
 * Proporciona un helper interno para reservar o anotar un hueco con estados
 * como pending_form, pending_payment o booked, respetando la disponibilidad,
 * zonas horarias y reglas del modelo.
 */

class SSA_Booking_Agent
{
	/** @var Simply_Schedule_Appointments */
	protected $plugin;

	public function __construct($plugin)
	{
		$this->plugin = $plugin;
	}

	/**
	 * Crea una cita si el hueco está disponible.
	 *
	 * Contrato rápido:
	 * - Inputs:
	 *   - $appointment_type_id: int|null, si es null intentará usar un default.
	 *   - $start: string fecha/hora de inicio del hueco. Por defecto se interpreta en la zona local del tipo.
	 *   - $status: 'pending_form' | 'pending_payment' | 'booked' (por defecto 'pending_form').
	 *   - $customer_information: array opcional (Name, Email, Phone, ...). Requerido si status != pending_form.
	 *   - $args: array opcional; soporta:
	 *       - 'input_timezone' => 'local'|'utc' (default 'local').
	 *
	 * - Output:
	 *   - array con 'result' => 'ok' y datos de la cita, o 'error' con 'code' y 'message'.
	 */
	public function reservar($appointment_type_id = null, $start = null, $status = 'pending_form', $customer_information = [], $args = [])
	{
		$appointment_type_id = (int) $appointment_type_id;

		// Resolver tipo por defecto si no viene.
		if ($appointment_type_id <= 0 && defined('SSA_DEFAULT_APPOINTMENT_TYPE_ID')) {
			$appointment_type_id = (int) constant('SSA_DEFAULT_APPOINTMENT_TYPE_ID');
		}
		if ($appointment_type_id <= 0) {
			$first = $this->plugin->appointment_type_model->query(['limit' => 1]);
			if (! empty($first[0]['id'])) {
				$appointment_type_id = (int) $first[0]['id'];
			}
		}
		if ($appointment_type_id <= 0) {
			return ['result' => 'error', 'code' => 'no_default_appointment_type', 'message' => 'No appointment_type_id provided and no default found.'];
		}

		// Normalizar estado: el modelo forzará a 'booked' cualquier estado distinto de 'pending_form'.
		// Para evitar confusiones, aceptamos 'booked' o 'pending_form'.
		// 'pending_payment' se degrada a 'pending_form' para conservar el hueco reservado.
		$status = (string) $status;
		if ('booked' === $status) {
			// ok
		} elseif ('pending_form' === $status || 'pending_payment' === $status) {
			$status = 'pending_form';
		} else {
			$status = 'pending_form';
		}

		// Normalizar start
		if (empty($start) || ! is_string($start)) {
			return ['result' => 'error', 'code' => 'invalid_start_date', 'message' => 'Start date is required.'];
		}
		// Permitir formato Y-m-d añadiendo 00:00:00
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
			$start .= ' 00:00:00';
		}

		$input_tz = isset($args['input_timezone']) && 'utc' === strtolower((string) $args['input_timezone']) ? 'utc' : 'local';

		try {
			// Obtener zona local del appointment type
			$local_tz = $this->plugin->utils->get_datetimezone($appointment_type_id);
		} catch (\Throwable $e) {
			return ['result' => 'error', 'code' => 'timezone_error', 'message' => $e->getMessage()];
		}

		// Convertir a UTC si es necesario
		try {
			if ('local' === $input_tz) {
				// $start viene en hora local del negocio -> pasar a UTC
				$utc_dt = SSA_Utils::get_datetime_in_utc($start, $local_tz);
			} else {
				// $start ya es UTC
				$utc_dt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
			}
		} catch (\Throwable $e) {
			return ['result' => 'error', 'code' => 'invalid_start_date', 'message' => 'Invalid start datetime.'];
		}

		// Cargar el objeto de tipo de cita
		try {
			$appointment_type = SSA_Appointment_Type_Object::instance($appointment_type_id);
			// accesamos una propiedad para forzar carga/validación
			$dummy = $appointment_type->title;
		} catch (\Throwable $e) {
			return ['result' => 'error', 'code' => 'appointment_type_not_found', 'message' => 'Appointment type not found.'];
		}

		// Comprobar disponibilidad del hueco con buffers, etc.
		$is_available = $this->plugin->appointment_model->is_prospective_appointment_available($appointment_type, $utc_dt);
		if (! $is_available) {
			return ['result' => 'error', 'code' => 'slot_unavailable', 'message' => 'The requested time is not available.'];
		}

		// Si no tenemos customer_information y el estado no es pending_form, degradar a pending_form
		if (empty($customer_information) && 'pending_form' !== $status) {
			$status = 'pending_form';
		}

		// Preparar payload para insert
		$data = [
			'appointment_type_id' => $appointment_type_id,
			'start_date' => $utc_dt->format('Y-m-d H:i:s'),
			'status' => $status,
			'customer_information' => $customer_information,
			'customer_timezone' => '', // evitar notice en insert()
		];

		// Insertar cita
		$result = $this->plugin->appointment_model->insert($data);
		if (is_wp_error($result)) {
			return ['result' => 'error', 'code' => 'wp_error', 'message' => implode('; ', wp_list_pluck($result->errors, 0))];
		}
		if (! is_numeric($result)) {
			// Puede devolver strings como 'invalid_start_date'
			return ['result' => 'error', 'code' => (string) $result, 'message' => 'Could not create appointment.'];
		}

		// Exponer tiempos en local y UTC para comodidad
		$local_dt = $this->plugin->utils->get_datetime_as_local_datetime($utc_dt, $appointment_type_id);

		return [
			'result' => 'ok',
			'appointment_id' => (int) $result,
			'status' => $status,
			'start_utc' => $utc_dt->format('Y-m-d H:i:s'),
			'start_local' => $local_dt->format('Y-m-d H:i:s'),
			'appointment_type' => $appointment_type_id,
		];
	}

	/**
	 * Confirma (convierte a 'booked') una cita ya reservada (pending_form), por ID.
	 * - Requiere que la cita exista y esté en estado reservado (pending_form).
	 * - Mezcla (merge) la información de cliente con la existente.
	 * - No cambia start_date ni tipo; por lo tanto no re-chequea disponibilidad.
	 *
	 * @param int   $appointment_id
	 * @param array $customer_information
	 * @param array $args Opcional. Puede incluir 'fetch' => [] para respuesta extendida futura.
	 * @return array
	 */
	public function confirmar_por_id($appointment_id, $customer_information = [], $args = [])
	{
		$appointment_id = (int) $appointment_id;
		if ($appointment_id <= 0) {
			return ['result' => 'error', 'code' => 'invalid_appointment_id', 'message' => 'Invalid appointment_id.'];
		}

		// Obtener datos actuales
		$existing = $this->plugin->appointment_model->get($appointment_id);
		if (empty($existing) || ! is_array($existing) || empty($existing['id'])) {
			return ['result' => 'error', 'code' => 'not_found', 'message' => 'Appointment not found.'];
		}

		if (empty($existing['status']) || 'pending_form' !== $existing['status']) {
			return ['result' => 'error', 'code' => 'not_reserved', 'message' => 'Appointment is not in a reserved state.'];
		}

		// Actualizar a booked y fusionar datos de cliente
		$update = ['status' => 'booked'];
		if (is_array($customer_information) && ! empty($customer_information)) {
			$update['customer_information'] = $customer_information; // merge ocurre vía filtro merge_customer_information
		}

		$resp = $this->plugin->appointment_model->update($appointment_id, $update);
		if (is_wp_error($resp)) {
			return ['result' => 'error', 'code' => 'wp_error', 'message' => implode('; ', wp_list_pluck($resp->errors, 0))];
		}

		return [
			'result' => 'ok',
			'appointment_id' => $appointment_id,
			'status' => 'booked',
		];
	}

	/**
	 * Confirma (convierte a 'booked') una cita reservada localizada por (appointment_type_id, start).
	 * - Útil si no se tiene el ID pero sí el slot original.
	 * - Si existe más de una coincidencia, devuelve error para evitar ambigüedades.
	 *
	 * @param int|null $appointment_type_id
	 * @param string   $start  Fecha/hora del hueco (por defecto interpretada en zona local del negocio)
	 * @param array    $customer_information
	 * @param array    $args { input_timezone: 'local'|'utc' }
	 * @return array
	 */
	public function confirmar($appointment_type_id = null, $start = null, $customer_information = [], $args = [])
	{
		$appointment_type_id = (int) $appointment_type_id;
		if ($appointment_type_id <= 0 && defined('SSA_DEFAULT_APPOINTMENT_TYPE_ID')) {
			$appointment_type_id = (int) constant('SSA_DEFAULT_APPOINTMENT_TYPE_ID');
		}
		if ($appointment_type_id <= 0) {
			$first = $this->plugin->appointment_type_model->query(['limit' => 1]);
			if (! empty($first[0]['id'])) {
				$appointment_type_id = (int) $first[0]['id'];
			}
		}
		if ($appointment_type_id <= 0) {
			return ['result' => 'error', 'code' => 'no_default_appointment_type', 'message' => 'No appointment_type_id provided and no default found.'];
		}

		if (empty($start) || ! is_string($start)) {
			return ['result' => 'error', 'code' => 'invalid_start_date', 'message' => 'Start date is required.'];
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
			$start .= ' 00:00:00';
		}

		$input_tz = isset($args['input_timezone']) && 'utc' === strtolower((string) $args['input_timezone']) ? 'utc' : 'local';
		try {
			$local_tz = $this->plugin->utils->get_datetimezone($appointment_type_id);
			if ('local' === $input_tz) {
				$utc_dt = SSA_Utils::get_datetime_in_utc($start, $local_tz);
			} else {
				$utc_dt = new DateTimeImmutable($start, new DateTimeZone('UTC'));
			}
		} catch (\Throwable $e) {
			return ['result' => 'error', 'code' => 'invalid_start_date', 'message' => 'Invalid start datetime.'];
		}

		// Buscar cita(s) pendientes exactamente en ese slot
		$rows = $this->plugin->appointment_model->query([
			'appointment_type_id' => $appointment_type_id,
			'status' => 'pending_form',
			'start_date_min' => $utc_dt->format('Y-m-d H:i:s'),
			'start_date_max' => $utc_dt->format('Y-m-d H:i:s'),
			'number' => 10,
			'orderby' => 'date_created',
			'order' => 'ASC',
		]);

		if (empty($rows)) {
			return ['result' => 'error', 'code' => 'not_found', 'message' => 'No reserved appointment found for that slot.'];
		}
		if (count($rows) > 1) {
			return ['result' => 'error', 'code' => 'ambiguous', 'message' => 'Multiple reservations found. Please confirm by appointment_id.'];
		}

		$appointment_id = (int) $rows[0]['id'];

		return $this->confirmar_por_id($appointment_id, $customer_information, $args);
	}
}

// Helper global (prefixed) + back-compat alias
if (! function_exists('aichat_ssa_reservar')) {
	/**
	 * Atajo global para reservar/anotar un hueco.
	 *
	 * @param int|null $appointment_type_id
	 * @param string   $start  Fecha/hora del hueco (por defecto local).
	 * @param string   $status 'pending_form'|'pending_payment'|'booked'
	 * @param array    $customer_information
	 * @param array    $args e.g. ['input_timezone' => 'local'|'utc']
	 * @return array result payload
	 */
	function aichat_ssa_reservar($appointment_type_id = null, $start = null, $status = 'pending_form', $customer_information = [], $args = [])
	{
		if (! function_exists('ssa') || ! isset(call_user_func('ssa')->booking_agent)) {
			return ['result' => 'error', 'code' => 'ssa_not_loaded', 'message' => 'SSA not loaded'];
		}

		return call_user_func('ssa')->booking_agent->reservar($appointment_type_id, $start, $status, $customer_information, $args);
	}
}
// Sin alias legacy ssa_reservar()

// Helper global: confirmar por ID
if (! function_exists('aichat_ssa_confirmar_por_id')) {
	/**
	 * Confirma una cita reservada (pending_form) a booked, por ID.
	 * @param int   $appointment_id
	 * @param array $customer_information
	 * @param array $args
	 * @return array
	 */
	function aichat_ssa_confirmar_por_id($appointment_id, $customer_information = [], $args = [])
	{
		if (! function_exists('ssa') || ! isset(call_user_func('ssa')->booking_agent)) {
			return ['result' => 'error', 'code' => 'ssa_not_loaded', 'message' => 'SSA not loaded'];
		}

		return call_user_func('ssa')->booking_agent->confirmar_por_id($appointment_id, $customer_information, $args);
	}
}
// Sin alias legacy ssa_confirmar_por_id()

// Helper global: confirmar por slot (tipo + inicio)
if (! function_exists('aichat_ssa_confirmar')) {
	/**
	 * Confirma una cita reservada localizada por (appointment_type_id, start)
	 * @param int|null $appointment_type_id
	 * @param string   $start
	 * @param array    $customer_information
	 * @param array    $args ['input_timezone' => 'local'|'utc']
	 * @return array
	 */
	function aichat_ssa_confirmar($appointment_type_id = null, $start = null, $customer_information = [], $args = [])
	{
		if (! function_exists('ssa') || ! isset(call_user_func('ssa')->booking_agent)) {
			return ['result' => 'error', 'code' => 'ssa_not_loaded', 'message' => 'SSA not loaded'];
		}

		return call_user_func('ssa')->booking_agent->confirmar($appointment_type_id, $start, $customer_information, $args);
	}
}
// Sin alias legacy ssa_confirmar()
