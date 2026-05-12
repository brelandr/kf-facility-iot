<?php
/**
 * REST: unauthenticated sensor readings (API key in body).
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KF_Facility_IoT_Rest_Sensor
 */
class KF_Facility_IoT_Rest_Sensor {

	const NAMESPACE = 'kf-iot/v1';

	const ROUTE = '/sensor/reading';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'handle_reading' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Merge JSON and form params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string, mixed>
	 */
	protected static function get_body_params( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}
		$form = $request->get_body_params();
		if ( is_array( $form ) ) {
			$params = array_merge( $params, $form );
		}
		return $params;
	}

	/**
	 * POST handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function handle_reading( $request ) {
		if ( ! KF_Facility_IoT_Install::logs_table_exists() ) {
			return new WP_Error(
				'ltkf_fiot_no_table',
				__( 'IoT logs table is not available.', 'kf-facility-iot' ),
				array( 'status' => 503 )
			);
		}

		$params = self::get_body_params( $request );

		$api_key = isset( $params['api_key'] ) ? (string) $params['api_key'] : '';
		$api_key = trim( $api_key );
		$stored  = (string) get_option( KF_Facility_IoT_Install::OPTION_API_KEY, '' );

		$valid = ( '' !== $stored && '' !== $api_key && strlen( $stored ) === strlen( $api_key ) && hash_equals( $stored, $api_key ) );
		if ( ! $valid ) {
			return new WP_Error(
				'ltkf_fiot_invalid_key',
				__( 'Invalid API key.', 'kf-facility-iot' ),
				array( 'status' => 401 )
			);
		}

		$kennel_id = isset( $params['kennel_id'] ) ? absint( $params['kennel_id'] ) : 0;
		if ( $kennel_id < 1 ) {
			return new WP_Error(
				'ltkf_fiot_bad_kennel',
				__( 'kennel_id is required.', 'kf-facility-iot' ),
				array( 'status' => 400 )
			);
		}

		$type = isset( $params['sensor_type'] ) ? sanitize_key( (string) $params['sensor_type'] ) : '';
		if ( '' === $type || ! in_array( $type, array( 'temperature', 'humidity' ), true ) ) {
			return new WP_Error(
				'ltkf_fiot_bad_type',
				__( 'sensor_type must be temperature or humidity.', 'kf-facility-iot' ),
				array( 'status' => 400 )
			);
		}

		if ( ! isset( $params['value'] ) || ! is_numeric( $params['value'] ) ) {
			return new WP_Error(
				'ltkf_fiot_bad_value',
				__( 'value must be numeric.', 'kf-facility-iot' ),
				array( 'status' => 400 )
			);
		}

		$value = (float) $params['value'];

		global $wpdb;

		$table = KF_Facility_IoT_Install::logs_table_name();
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- IoT log insert; table from install helper.
		$ok = $wpdb->insert(
			$table,
			array(
				'kennel_id'     => $kennel_id,
				'sensor_type'   => $type,
				'reading_value' => $value,
				'created_gmt'   => $now,
			),
			array( '%d', '%s', '%f', '%s' )
		);

		if ( false === $ok ) {
			return new WP_Error(
				'ltkf_fiot_insert_failed',
				__( 'Could not save reading.', 'kf-facility-iot' ),
				array( 'status' => 500 )
			);
		}

		$new_id = (int) $wpdb->insert_id;

		if ( 'temperature' === $type ) {
			KF_Facility_IoT_Alerts::maybe_fire_high_temperature_alert( $kennel_id, $value );
		}

		return rest_ensure_response(
			array(
				'ok'        => true,
				'id'        => $new_id,
				'created_gmt' => $now,
			)
		);
	}
}
