<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Simple Availability Agent Wrapper.
 *
 * Proporciona una función de alto nivel para obtener huecos libres (start datetimes)
 * usando la lógica ya existente de `SSA_Availability_Model::availability_query`.
 *
 * Uso básico:
 *   $slots = ssa()->availability_agent->disponibilidad( 12 );
 *   // ó con rango definido
 *   $slots = ssa()->availability_agent->disponibilidad( 12, '2025-10-10 00:00:00', '2025-10-17 23:59:59' );
 *
 * También se expone helper global `ssa_disponibilidad()`.
 *
 * NOTA: Se necesita siempre el appointment_type_id para delimitar la disponibilidad.
 */
use League\Period\Period; // Asegura el uso directo si no está ya importado en otro contexto

class SSA_Availability_Agent {
	/** @var Simply_Schedule_Appointments */
	protected $plugin;

	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Devuelve un array de strings ("Y-m-d H:i:s") con los start_date bookeables.
	 *
	 * @param int|null    $appointment_type_id  ID del tipo de cita. Si es null/0 intentará usar un valor por defecto.
	 * @param string|null $start  Fecha/hora inicial (Y-m-d H:i:s). Por defecto: ahora.
	 * @param string|null $end    Fecha/hora final (Y-m-d H:i:s). Por defecto: start + 7 días.
	 * @return array|string  Array de start_date o string 'appointment_type_not_found' / 'invalid_params'.
	 */
	public function disponibilidad( $appointment_type_id = null, $start = null, $end = null ) {
		$appointment_type_id = (int) $appointment_type_id;

		// Fallback: constante definida por el usuario.
		if ( $appointment_type_id <= 0 && defined( 'SSA_DEFAULT_APPOINTMENT_TYPE_ID' ) ) {
			$appointment_type_id = (int) constant( 'SSA_DEFAULT_APPOINTMENT_TYPE_ID' );
		}

		// Fallback: primer appointment type existente.
		if ( $appointment_type_id <= 0 ) {
			$first = $this->plugin->appointment_type_model->query( array( 'limit' => 1 ) );
			if ( ! empty( $first[0]['id'] ) ) {
				$appointment_type_id = (int) $first[0]['id'];
			}
		}

		if ( $appointment_type_id <= 0 ) {
			return 'no_default_appointment_type';
		}

		if ( empty( $start ) ) {
			// Usar UTC para mantener coherencia: SSA guarda/espera fechas base en UTC
			$start = current_time( 'mysql', true ); // UTC, formato Y-m-d H:i:s
		}

		if ( empty( $end ) ) {
			$dtStart = new DateTimeImmutable( $start );
			$end     = $dtStart->add( new DateInterval( 'P7D' ) )->format( 'Y-m-d H:i:s' );
		}

		// Si el parámetro end viene en formato Y-m-d sin hora, usar fin de día
		if ( is_string( $end ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ) {
			$end .= ' 23:59:59';
		}

		// Usamos directamente las clases internas para evitar overhead de WP_REST_Request
		try {
			$appointment_type = SSA_Appointment_Type_Object::instance( $appointment_type_id );
			// Acceso a título fuerza excepción si no existe
			$dummy = $appointment_type->title;
		} catch ( \Exception $e ) {
			return 'appointment_type_not_found';
		}

		$start_dt = ssa_datetime( $start );
		$end_dt   = ssa_datetime( $end );
		$period   = new Period( $start_dt, $end_dt );

		$availability_query = new SSA_Availability_Query(
			$appointment_type,
			$period,
			array(
				'cache_level_read'  => 1,
				'cache_level_write' => 1,
			)
		);
		$bookable = $availability_query->get_bookable_appointment_start_datetime_strings();
		if ( ! is_array( $bookable ) ) {
			return array();
		}

		// Normalizar a array de strings y convertir a la zona local configurada
		$slot_strings = array();
		foreach ( $bookable as $item ) {
			$start_string = is_array( $item ) && isset( $item['start_date'] ) ? $item['start_date'] : ( is_string( $item ) ? $item : null );
			if ( ! $start_string ) { continue; }
			try {
				$local_dt = $this->plugin->utils->get_datetime_as_local_datetime( $start_string, $appointment_type_id );
				$slot_strings[] = $local_dt->format( 'Y-m-d H:i:s' );
			} catch ( \Throwable $e ) {
				// Si falla la conversión, devolvemos el original por seguridad
				$slot_strings[] = $start_string;
			}
		}

		return $slot_strings;
	}
}

// Helper global opcional (prefixed) y alias sin prefijo para compatibilidad.
if ( ! function_exists( 'aichat_ssa_disponibilidad' ) ) {
	/**
	 * Atajo global a la disponibilidad.
	 * @param int $appointment_type_id
	 * @param string|null $start
	 * @param string|null $end
	 * @return array|string
	 */
	function aichat_ssa_disponibilidad( $appointment_type_id = null, $start = null, $end = null ) {
		if ( ! function_exists( 'ssa' ) ) {
			return 'ssa_not_loaded';
		}
		return call_user_func('ssa')->availability_agent->disponibilidad( $appointment_type_id, $start, $end );
	}
}
// Nota: sin alias de retrocompatibilidad no prefijado, por solicitud.
