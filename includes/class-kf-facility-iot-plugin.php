<?php
/**
 * Main loader.
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-install.php';
require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-alerts.php';
require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-rest-sensor.php';
require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-smart-lock.php';
require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-admin-settings.php';

/**
 * Class KF_Facility_IoT_Plugin
 */
class KF_Facility_IoT_Plugin {

	/**
	 * Instance.
	 *
	 * @var self|null
	 */
	protected static $instance = null;

	/**
	 * Singleton.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		KF_Facility_IoT_Install::maybe_upgrade();
		KF_Facility_IoT_Rest_Sensor::init();
		KF_Facility_IoT_Alerts::init();
		KF_Facility_IoT_Smart_Lock::init();
		KF_Facility_IoT_Admin_Settings::init();
	}
}
