<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Settings {
	const OPTION_NAME = 'yy_dmm_auto_post_settings';

	public static function defaults() {
		return array(
			'api_id'             => '',
			'affiliate_id'       => '',
			'site'               => 'FANZA',
			'service'            => 'digital',
			'floor'              => 'videoc',
			'keyword'            => '',
			'sort'               => 'date',
			'hits'               => 50,
			'post_status'        => 'draft',
			'post_type'          => 'post',
			'parent_category_id' => 0,
			'create_categories'  => 1,
			'create_tags'        => 1,
			'max_posts'          => 1,
			'prevent_duplicates' => 1,
			'enable_cron'        => 0,
			'cron_interval'      => 'daily',
			'daily_time'         => '03:00',
			'save_logs'          => 1,
			'delete_on_uninstall'=> 0,
		);
	}

	public static function ensure_defaults() {
		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, self::defaults(), '', false );
		}
	}

	public static function get() {
		$saved = get_option( self::OPTION_NAME, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	public static function update( $settings ) {
		update_option( self::OPTION_NAME, self::sanitize( $settings ), false );
	}

	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$settings = self::defaults();

		$settings['api_id']       = sanitize_text_field( $input['api_id'] ?? '' );
		$settings['affiliate_id'] = sanitize_text_field( $input['affiliate_id'] ?? '' );
		$settings['site']         = sanitize_text_field( $input['site'] ?? 'FANZA' );
		$settings['service']      = sanitize_key( $input['service'] ?? 'digital' );
		$settings['floor']        = sanitize_key( $input['floor'] ?? 'videoc' );
		$settings['keyword']      = sanitize_text_field( $input['keyword'] ?? '' );
		$settings['sort']         = sanitize_key( $input['sort'] ?? 'date' );
		$settings['hits']         = max( 1, min( 100, absint( $input['hits'] ?? 50 ) ) );

		$post_status = sanitize_key( $input['post_status'] ?? 'draft' );
		$settings['post_status'] = in_array( $post_status, array( 'draft', 'publish' ), true ) ? $post_status : 'draft';

		$settings['post_type']          = sanitize_key( $input['post_type'] ?? 'post' );
		$settings['parent_category_id'] = absint( $input['parent_category_id'] ?? 0 );
		$settings['create_categories']  = ! empty( $input['create_categories'] ) ? 1 : 0;
		$settings['create_tags']        = ! empty( $input['create_tags'] ) ? 1 : 0;
		$settings['max_posts']          = max( 1, min( 50, absint( $input['max_posts'] ?? 1 ) ) );
		$settings['prevent_duplicates'] = ! empty( $input['prevent_duplicates'] ) ? 1 : 0;
		$settings['enable_cron']        = ! empty( $input['enable_cron'] ) ? 1 : 0;

		$cron_interval = sanitize_key( $input['cron_interval'] ?? 'daily' );
		$allowed       = array( 'daily', 'twicedaily', 'sixhourly' );
		$settings['cron_interval'] = in_array( $cron_interval, $allowed, true ) ? $cron_interval : 'daily';

		$daily_time = sanitize_text_field( $input['daily_time'] ?? '03:00' );
		if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $daily_time ) ) {
			$daily_time = '03:00';
		}
		$settings['daily_time'] = $daily_time;

		$settings['save_logs']           = ! empty( $input['save_logs'] ) ? 1 : 0;
		$settings['delete_on_uninstall'] = ! empty( $input['delete_on_uninstall'] ) ? 1 : 0;

		return $settings;
	}
}
