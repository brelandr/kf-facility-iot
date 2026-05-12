<?php
/**
 * Admin: API key and critical temperature.
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KF_Facility_IoT_Admin_Settings
 */
class KF_Facility_IoT_Admin_Settings {

	const PAGE_SLUG = 'kf-facility-iot';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_kf_fiot_regenerate_key', array( __CLASS__, 'handle_regenerate_key' ) );
	}

	/**
	 * Submenu under KennelFlow pets.
	 *
	 * @return void
	 */
	public static function register_menu() {
		if ( ! function_exists( 'ltkf_get_pet_post_type' ) ) {
			return;
		}

		$parent = function_exists( 'ltkf_get_hub_menu_slug' ) ? ltkf_get_hub_menu_slug() : 'edit.php?post_type=' . ltkf_get_pet_post_type();
		add_submenu_page(
			$parent,
			__( 'IoT Hardware', 'kf-facility-iot' ),
			__( 'IoT Hardware', 'kf-facility-iot' ),
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Register options.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'ltkf_fiot_settings',
			KF_Facility_IoT_Install::OPTION_CRITICAL_HIGH_F,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_critical_high' ),
				'default'           => '80',
			)
		);

		register_setting(
			'ltkf_fiot_settings',
			KF_Facility_IoT_Install::OPTION_SMART_LOCK_API_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_smart_lock_api_url' ),
				'default'           => '',
			)
		);
	}

	/**
	 * Sanitize smart lock outbound API URL.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_smart_lock_api_url( $value ) {
		if ( null === $value || is_array( $value ) ) {
			return '';
		}
		return esc_url_raw( trim( (string) $value ) );
	}

	/**
	 * Sanitize critical high temperature.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	public static function sanitize_critical_high( $value ) {
		if ( null === $value || is_array( $value ) ) {
			return '80';
		}
		$n = (float) $value;
		if ( $n < -40 ) {
			$n = -40.0;
		}
		if ( $n > 200 ) {
			$n = 200.0;
		}
		return (string) $n;
	}

	/**
	 * Regenerate inbound API key.
	 *
	 * @return void
	 */
	public static function handle_regenerate_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'kf-facility-iot' ) );
		}

		check_admin_referer( 'ltkf_fiot_regenerate_key' );

		update_option( KF_Facility_IoT_Install::OPTION_API_KEY, wp_generate_password( 48, false, false ) );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type' => ltkf_get_pet_post_type(),
					'page'      => self::PAGE_SLUG,
					'updated'   => 'key',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Settings page.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'kf-facility-iot' ) );
		}

		$key            = (string) get_option( KF_Facility_IoT_Install::OPTION_API_KEY, '' );
		$critical       = get_option( KF_Facility_IoT_Install::OPTION_CRITICAL_HIGH_F, 80 );
		$smart_lock_url = (string) get_option( KF_Facility_IoT_Install::OPTION_SMART_LOCK_API_URL, '' );
		$rest_url       = esc_url_raw( rest_url( KF_Facility_IoT_Rest_Sensor::NAMESPACE . KF_Facility_IoT_Rest_Sensor::ROUTE ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		if ( isset( $_GET['updated'] ) && 'key' === $_GET['updated'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
				esc_html__( 'A new API key was generated. Update your sensors.', 'kf-facility-iot' )
			);
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'POST sensor readings to the REST endpoint below using the shared API key. Temperatures are expected in °F for alert thresholds.', 'kf-facility-iot' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Sensor webhook URL', 'kf-facility-iot' ); ?></th>
					<td><code><?php echo esc_html( $rest_url ); ?></code></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'API key', 'kf-facility-iot' ); ?></th>
					<td>
						<code style="word-break: break-all;"><?php echo esc_html( $key ); ?></code>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
							<?php wp_nonce_field( 'ltkf_fiot_regenerate_key' ); ?>
							<input type="hidden" name="action" value="ltkf_fiot_regenerate_key" />
							<?php submit_button( __( 'Regenerate API key', 'kf-facility-iot' ), 'secondary small', '', false ); ?>
						</form>
					</td>
				</tr>
			</table>

			<form action="options.php" method="post">
				<?php settings_fields( 'ltkf_fiot_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="ltkf_fiot_smart_lock_api_url"><?php esc_html_e( 'Smart lock API URL', 'kf-facility-iot' ); ?></label>
						</th>
						<td>
							<input
								name="<?php echo esc_attr( KF_Facility_IoT_Install::OPTION_SMART_LOCK_API_URL ); ?>"
								id="ltkf_fiot_smart_lock_api_url"
								type="url"
								class="regular-text code"
								value="<?php echo esc_attr( $smart_lock_url ); ?>"
								placeholder="https://"
							/>
							<p class="description">
								<?php esc_html_e( 'When a calendar booking is moved to a kennel with a smart lock ID, an unlock command is POSTed to this URL (JSON body: action, lock_id). Leave empty to disable.', 'kf-facility-iot' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ltkf_fiot_critical_high"><?php esc_html_e( 'Critical high temperature (°F)', 'kf-facility-iot' ); ?></label>
						</th>
						<td>
							<input
								name="<?php echo esc_attr( KF_Facility_IoT_Install::OPTION_CRITICAL_HIGH_F ); ?>"
								id="ltkf_fiot_critical_high"
								type="number"
								step="0.1"
								class="small-text"
								value="<?php echo esc_attr( (string) $critical ); ?>"
							/>
							<p class="description">
								<?php esc_html_e( 'When a temperature reading exceeds this value, an email is sent to the admin address and a dashboard notice is shown.', 'kf-facility-iot' ); ?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
