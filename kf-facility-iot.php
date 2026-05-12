<?php
/**
 * Plugin Name:       KF Facility IoT
 * Plugin URI:         https://github.com/landtechwebdesigns/kf-facility-iot
 * Description:        KennelFlow add-on: sensor webhooks, temperature alerts, and smart hardware bridges for facility management.
 * Version:            0.1.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Requires Plugins:   kennelflow-core
 * Author:             LandTech Web Designs
 * License:            GPL-2.0-or-later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        kf-facility-iot
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

define( 'KF_FIOT_VERSION', '0.1.0' );
define( 'KF_FIOT_PLUGIN_FILE', __FILE__ );
define( 'KF_FIOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KF_FIOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KF_FIOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-install.php';
require_once KF_FIOT_PLUGIN_DIR . 'includes/class-kf-facility-iot-plugin.php';

register_activation_hook( KF_FIOT_PLUGIN_FILE, array( 'KF_Facility_IoT_Install', 'activate' ) );

/**
 * Bootstrap when KennelFlow Core is present.
 *
 * @return void
 */
function kf_fiot_bootstrap() {
	if ( ! defined( 'LTKF_CORE_VERSION' ) ) {
		return;
	}
	KF_Facility_IoT_Plugin::instance()->init();
}

add_action( 'plugins_loaded', 'ltkf_fiot_bootstrap', 20 );
