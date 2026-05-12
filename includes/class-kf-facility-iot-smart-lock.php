<?php
/**
 * Smart lock unlock when a calendar booking is moved to a kennel with a lock ID.
 *
 * @package KF_Facility_IoT
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class KF_Facility_IoT_Smart_Lock
 */
class KF_Facility_IoT_Smart_Lock {

	/**
	 * Post meta on kennel/room CPT: hardware smart lock identifier.
	 */
	const KENNEL_POST_META_LOCK_ID = '_kf_iot_smart_lock_id';

	/**
	 * Term meta key (when kennel is linked via taxonomy terms).
	 */
	const TERM_META_LOCK_ID = 'ltkf_iot_smart_lock_id';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_term_meta' ), 15 );
		add_filter( 'ltkf_rest_calendar_booking_patched', array( __CLASS__, 'on_booking_patched' ), 10, 3 );
	}

	/**
	 * Register term meta for smart lock id on taxonomies supplied via filter.
	 *
	 * @return void
	 */
	public static function register_term_meta() {
		/**
		 * Taxonomies whose terms may store `kf_iot_smart_lock_id` (term meta).
		 *
		 * @param string[] $taxonomies Taxonomy slugs.
		 */
		$taxonomies = apply_filters( 'ltkf_iot_smart_lock_term_taxonomies', array() );

		foreach ( (array) $taxonomies as $taxonomy ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy ) {
				continue;
			}
			register_term_meta(
				$taxonomy,
				self::TERM_META_LOCK_ID,
				array(
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'single'            => true,
					'show_in_rest'      => true,
				)
			);
		}
	}

	/**
	 * Resolve smart lock ID: post meta on kennel, then term meta (filterable taxonomies).
	 *
	 * @param int $kennel_id Kennel or room post ID.
	 * @return string Non-empty lock id or empty string.
	 */
	public static function get_smart_lock_id_for_kennel( $kennel_id ) {
		$kennel_id = absint( $kennel_id );
		if ( $kennel_id < 1 ) {
			return '';
		}

		$direct = get_post_meta( $kennel_id, self::KENNEL_POST_META_LOCK_ID, true );
		if ( is_string( $direct ) && '' !== trim( $direct ) ) {
			return trim( $direct );
		}
		if ( is_numeric( $direct ) ) {
			return (string) $direct;
		}

		/**
		 * Taxonomy slugs to inspect for term meta `kf_iot_smart_lock_id` on terms assigned to this post.
		 *
		 * @param string[] $taxonomies Taxonomy names.
		 * @param int      $kennel_id  Post ID.
		 */
		$taxonomies = apply_filters( 'ltkf_iot_smart_lock_kennel_taxonomies', array(), $kennel_id );

		foreach ( (array) $taxonomies as $taxonomy ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( '' === $taxonomy ) {
				continue;
			}
			$terms = wp_get_post_terms( $kennel_id, $taxonomy, array( 'fields' => 'ids' ) );
			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term_id ) {
				$tid = get_term_meta( absint( $term_id ), self::TERM_META_LOCK_ID, true );
				if ( is_string( $tid ) && '' !== trim( $tid ) ) {
					return trim( $tid );
				}
				if ( is_numeric( $tid ) ) {
					return (string) $tid;
				}
			}
		}

		/**
		 * Filters resolved smart lock id (last chance).
		 *
		 * @param string $lock_id   Lock id or empty.
		 * @param int    $kennel_id Kennel post ID.
		 */
		return (string) apply_filters( 'ltkf_iot_smart_lock_id_for_kennel', '', $kennel_id );
	}

	/**
	 * Whether the post is a physical kennel/room resource (not a groomer user id, etc.).
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	protected static function is_kennel_or_room_post( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id < 1 ) {
			return false;
		}

		$pt = get_post_type( $post_id );
		if ( ! is_string( $pt ) || '' === $pt ) {
			return false;
		}

		$allowed = array( 'kennelpress_kennel', 'kennelflow_vet_room' );

		/**
		 * Post types that may carry a smart lock resource id.
		 *
		 * @param string[] $allowed   Post type names.
		 * @param int      $post_id   Post ID.
		 */
		$allowed = apply_filters( 'ltkf_iot_smart_lock_resource_post_types', $allowed, $post_id );

		return in_array( $pt, array_map( 'sanitize_key', (array) $allowed ), true );
	}

	/**
	 * After calendar PATCH: if resource changed to a kennel with a lock ID, POST unlock.
	 *
	 * @param array           $booking    Normalized booking.
	 * @param WP_REST_Request $request    Request.
	 * @param object|null     $row_before kf_bookings row before UPDATE.
	 * @return array
	 */
	public static function on_booking_patched( $booking, $request, $row_before ) {
		if ( ! is_array( $booking ) || ! $request instanceof WP_REST_Request ) {
			return $booking;
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) || ! array_key_exists( 'resource_id', $params ) ) {
			return $booking;
		}

		if ( ! is_object( $row_before ) ) {
			return $booking;
		}

		$new_id = isset( $booking['resource_id'] ) ? absint( $booking['resource_id'] ) : 0;
		$old_id = isset( $row_before->kennel_id ) ? absint( $row_before->kennel_id ) : 0;

		if ( $new_id < 1 || $new_id === $old_id ) {
			return $booking;
		}

		if ( ! self::is_kennel_or_room_post( $new_id ) ) {
			return $booking;
		}

		$api_url = get_option( KF_Facility_IoT_Install::OPTION_SMART_LOCK_API_URL, '' );
		$api_url = is_string( $api_url ) ? trim( $api_url ) : '';
		if ( '' === $api_url ) {
			return $booking;
		}

		$lock_id = self::get_smart_lock_id_for_kennel( $new_id );
		if ( '' === $lock_id ) {
			return $booking;
		}

		$payload = apply_filters(
			'ltkf_iot_smart_lock_unlock_payload',
			array(
				'action'  => 'unlock',
				'lock_id' => $lock_id,
			),
			$new_id,
			$booking
		);

		$body = wp_json_encode( $payload );
		if ( false === $body ) {
			$body = '{}';
		}

		$response = wp_remote_post(
			esc_url_raw( $api_url ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
				'body'    => $body,
			)
		);

		unset( $response );

		self::maybe_audit_log( $new_id );

		return $booking;
	}

	/**
	 * Append KennelFlow Vet EMR audit row when available.
	 *
	 * @param int $kennel_id Kennel post ID.
	 * @return void
	 */
	protected static function maybe_audit_log( $kennel_id ) {
		if ( ! class_exists( 'KennelFlow_Vet_EMR_Audit' ) || ! class_exists( 'KennelFlow_Vet_Install' ) ) {
			return;
		}

		if ( ! function_exists( 'ltkf_table_exists' ) || ! ltkf_table_exists( KennelFlow_Vet_Install::audit_table() ) ) {
			return;
		}

		$message = sprintf(
			/* translators: %d: kennel post ID */
			__( 'Smart Lock Unlocked for Kennel %d', 'kf-facility-iot' ),
			absint( $kennel_id )
		);

		KennelFlow_Vet_EMR_Audit::log(
			'ltkf_iot',
			absint( $kennel_id ),
			'smart_lock_unlock',
			null,
			$message,
			get_current_user_id()
		);
	}
}
