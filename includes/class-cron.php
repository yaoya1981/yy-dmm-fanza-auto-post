<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Cron {
	const HOOK = 'yy_dmm_auto_post_cron_event';

	private $plugin;

	public function __construct( YY_DMM_Auto_Post_Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'add_schedules' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	public function add_schedules( $schedules ) {
		return self::register_sixhourly_schedule( $schedules );
	}

	public static function register_sixhourly_schedule( $schedules ) {
		$schedules['sixhourly'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => '6時間ごと',
		);

		return $schedules;
	}

	public function run() {
		$this->plugin->run_import( 'auto' );
	}

	public static function reschedule() {
		add_filter( 'cron_schedules', array( __CLASS__, 'register_sixhourly_schedule' ) );
		self::clear();

		$settings = YY_DMM_Auto_Post_Settings::get();
		if ( empty( $settings['enable_cron'] ) ) {
			return;
		}

		$schedule = 'daily';
		if ( 'twicedaily' === $settings['cron_interval'] ) {
			$schedule = 'twicedaily';
		} elseif ( 'sixhourly' === $settings['cron_interval'] ) {
			$schedule = 'sixhourly';
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( self::next_timestamp( $settings ), $schedule, self::HOOK );
		}
	}

	public static function clear() {
		wp_clear_scheduled_hook( self::HOOK );
	}

	private static function next_timestamp( $settings ) {
		$time = isset( $settings['daily_time'] ) ? $settings['daily_time'] : '03:00';
		if ( ! preg_match( '/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $matches ) ) {
			$matches = array( '', '03', '00' );
		}

		$timezone = wp_timezone();
		$now = new DateTimeImmutable( 'now', $timezone );
		$next = $now->setTime( absint( $matches[1] ), absint( $matches[2] ) );

		if ( $next->getTimestamp() <= $now->getTimestamp() ) {
			$next = $next->modify( '+1 day' );
		}

		return $next->getTimestamp();
	}
}
