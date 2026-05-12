<?php
/**
 * Database schema for IoT sensor logs.
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KF_Facility_IoT_Install
 */
class KF_Facility_IoT_Install {

	const DB_VERSION_OPTION = 'ltkf_fiot_db_version';

	const SCHEMA_VERSION = '1';

	/**
	 * Option: inbound API key for sensor webhook.
	 */
	const OPTION_API_KEY = 'ltkf_fiot_api_key';

	/**
	 * Option: critical high temperature (Fahrenheit).
	 */
	const OPTION_CRITICAL_HIGH_F = 'ltkf_fiot_critical_high_f';

	/**
	 * Option: outbound smart lock API base URL (POST unlock commands).
	 */
	const OPTION_SMART_LOCK_API_URL = 'iot_smart_lock_api_url';

	/**
	 * Table name including prefix.
	 *
	 * @return string
	 */
	public static function logs_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'ltkf_iot_logs';
	}

	/**
	 * Create tables and default options.
	 *
	 * @return void
	 */
	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::logs_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			kennel_id bigint(20) unsigned NOT NULL DEFAULT 0,
			sensor_type varchar(32) NOT NULL DEFAULT '',
			reading_value decimal(12,4) NOT NULL DEFAULT 0,
			created_gmt datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY kennel_created (kennel_id, created_gmt),
			KEY sensor_type (sensor_type)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( '' === (string) get_option( self::OPTION_API_KEY, '' ) ) {
			update_option( self::OPTION_API_KEY, wp_generate_password( 48, false, false ) );
		}

		if ( false === get_option( self::OPTION_CRITICAL_HIGH_F, false ) ) {
			update_option( self::OPTION_CRITICAL_HIGH_F, 80 );
		}

		if ( false === get_option( self::OPTION_SMART_LOCK_API_URL, false ) ) {
			add_option( self::OPTION_SMART_LOCK_API_URL, '', '', false );
		}

		update_option( self::DB_VERSION_OPTION, self::SCHEMA_VERSION );
	}

	/**
	 * Activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Upgrade if needed.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$v = get_option( self::DB_VERSION_OPTION, '' );
		if ( self::SCHEMA_VERSION === $v ) {
			return;
		}
		self::install();
	}

	/**
	 * Whether the logs table exists.
	 *
	 * @return bool
	 */
	public static function logs_table_exists() {
		global $wpdb;

		if ( ! function_exists( 'ltkf_table_exists' ) ) {
			return false;
		}

		return ltkf_table_exists( self::logs_table_name() );
	}
}
