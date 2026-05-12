<?php
/**
 * Temperature alerts: email + admin notice.
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KF_Facility_IoT_Alerts
 */
class KF_Facility_IoT_Alerts {

	const OPTION_PENDING_NOTICE = 'ltkf_fiot_pending_dashboard_notice';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_notices', array( __CLASS__, 'render_pending_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_notice' ) );
	}

	/**
	 * Human label for a kennel/resource ID (post title when available).
	 *
	 * @param int $kennel_id Kennel or room post ID.
	 * @return string
	 */
	public static function get_kennel_display_name( $kennel_id ) {
		$kennel_id = absint( $kennel_id );
		if ( $kennel_id < 1 ) {
			return __( 'Unknown kennel', 'kf-facility-iot' );
		}

		$post = get_post( $kennel_id );
		if ( $post && 'trash' !== $post->post_status ) {
			$title = get_the_title( $post );
			if ( is_string( $title ) && '' !== trim( $title ) ) {
				/**
				 * Filters display name for a kennel_id in IoT alerts.
				 *
				 * @param string $name      Resolved title.
				 * @param int    $kennel_id ID.
				 */
				return (string) apply_filters( 'ltkf_facility_iot_kennel_display_name', $title, $kennel_id );
			}
		}

		/**
		 * Filters display name for a kennel_id in IoT alerts.
		 *
		 * @param string $name      Default name.
		 * @param int    $kennel_id ID.
		 */
		return (string) apply_filters( 'ltkf_facility_iot_kennel_display_name', __( 'Unknown kennel', 'kf-facility-iot' ), $kennel_id );
	}

	/**
	 * Fire email + store dashboard notice when temperature exceeds threshold.
	 *
	 * @param int   $kennel_id Kennel / resource post ID.
	 * @param float $value_f   Temperature in °F.
	 * @return void
	 */
	public static function maybe_fire_high_temperature_alert( $kennel_id, $value_f ) {
		$kennel_id = absint( $kennel_id );
		$value_f   = (float) $value_f;

		$threshold = get_option( KF_Facility_IoT_Install::OPTION_CRITICAL_HIGH_F, 80 );
		$threshold = is_numeric( $threshold ) ? (float) $threshold : 80.0;

		if ( $value_f <= $threshold ) {
			return;
		}

		$name = self::get_kennel_display_name( $kennel_id );

		$subject = sprintf(
			/* translators: 1: site name, 2: kennel name */
			__( '[%1$s] URGENT: High temperature — %2$s', 'kf-facility-iot' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			$name
		);

		$body = sprintf(
			/* translators: 1: kennel name, 2: temperature, 3: threshold */
			__( "Temperature alert\n\nKennel: %1$s\nReading: %2$s °F\nCritical high threshold: %3$s °F\n\nTime (UTC): %4$s\n", 'kf-facility-iot' ),
			$name,
			number_format_i18n( $value_f, 1 ),
			number_format_i18n( $threshold, 1 ),
			gmdate( 'Y-m-d H:i:s' )
		);

		$admin = sanitize_email( (string) get_option( 'admin_email' ) );
		if ( is_email( $admin ) ) {
			wp_mail( $admin, $subject, $body );
		}

		$notice_key = (string) wp_generate_password( 12, false, false );

		update_option(
			self::OPTION_PENDING_NOTICE,
			array(
				'key'       => $notice_key,
				'kennel_id' => $kennel_id,
				'value_f'   => $value_f,
				'name'      => $name,
				'time'      => time(),
			),
			false
		);
	}

	/**
	 * Dismiss notice via query args.
	 *
	 * @return void
	 */
	public static function maybe_dismiss_notice() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Dismiss action; verified below.
		if ( empty( $_GET['ltkf_fiot_dismiss'] ) || empty( $_GET['_wpnonce'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'ltkf_fiot_dismiss_notice' ) ) {
			return;
		}

		delete_option( self::OPTION_PENDING_NOTICE );

		wp_safe_redirect( remove_query_arg( array( 'ltkf_fiot_dismiss', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Output dismissible urgent notice.
	 *
	 * @return void
	 */
	public static function render_pending_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$data = get_option( self::OPTION_PENDING_NOTICE, null );
		if ( ! is_array( $data ) || empty( $data['key'] ) ) {
			return;
		}

		$name    = isset( $data['name'] ) ? (string) $data['name'] : __( 'Unknown kennel', 'kf-facility-iot' );
		$value_f = isset( $data['value_f'] ) ? (float) $data['value_f'] : 0.0;

		$dismiss = wp_nonce_url(
			add_query_arg( 'ltkf_fiot_dismiss', '1', admin_url() ),
			'ltkf_fiot_dismiss_notice'
		);

		$line = sprintf(
			/* translators: 1: kennel name, 2: temperature */
			__( 'URGENT: Temperature in %1$s is %2$s °F!', 'kf-facility-iot' ),
			$name,
			number_format_i18n( $value_f, 1 )
		);

		printf(
			'<div class="notice notice-error"><p><strong>%s</strong></p><p><a href="%s" class="button">%s</a></p></div>',
			esc_html( $line ),
			esc_url( $dismiss ),
			esc_html__( 'Dismiss alert', 'kf-facility-iot' )
		);
	}
}
