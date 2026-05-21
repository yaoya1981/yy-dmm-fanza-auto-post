<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Logger {
	const OPTION_NAME = 'yy_dmm_auto_post_logs';
	const MAX_LOGS    = 100;

	public function add( $type, $result ) {
		$settings = YY_DMM_Auto_Post_Settings::get();
		if ( empty( $settings['save_logs'] ) ) {
			return;
		}

		$logs = self::get_logs();
		$logs[] = array(
			'datetime' => current_time( 'mysql' ),
			'type'     => sanitize_text_field( $type ),
			'fetched'  => absint( $result['fetched'] ?? 0 ),
			'posted'   => absint( $result['posted'] ?? 0 ),
			'skipped'  => absint( $result['skipped'] ?? 0 ),
			'errors'   => self::sanitize_errors( $result['errors'] ?? array() ),
		);

		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, - self::MAX_LOGS );
		}

		update_option( self::OPTION_NAME, $logs, false );
	}

	public static function get_logs() {
		$logs = get_option( self::OPTION_NAME, array() );
		return is_array( $logs ) ? $logs : array();
	}

	private static function sanitize_errors( $errors ) {
		if ( ! is_array( $errors ) ) {
			return array();
		}

		$clean = array();
		foreach ( array_slice( $errors, 0, 20 ) as $error ) {
			$clean[] = sanitize_text_field( wp_strip_all_tags( (string) $error ) );
		}

		return $clean;
	}
}
