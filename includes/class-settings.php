<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Settings {
	const OPTION_NAME = 'yy_dmm_auto_post_settings';

	public static function taxonomy_iteminfo_options() {
		return array(
			'genre'    => 'ジャンル',
			'maker'    => 'メーカー',
			'label'    => 'レーベル',
			'actress'  => '女優',
			'director' => '監督',
		);
	}

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
			'sync_post_date_with_release_date' => 0,
			'sample_movie_size'  => 'size_720_480',
			'featured_image_source' => 'image_small',
			'sample_image_size'  => 'sample_l',
			'sample_image_max'   => 0,
			'show_sample_image_continue_button' => 0,
			'sample_image_continue_button_text' => '続きを見る',
			'title_template'     => '{title}｜{label}',
			'body_sections'      => array(
				'sample_movie'        => 1,
				'top_affiliate_button'=> 1,
				'product_info'        => 1,
				'description'         => 1,
				'sample_images'       => 1,
				'bottom_affiliate_button' => 1,
			),
			'body_section_order' => array(
				'sample_movie'        => 10,
				'top_affiliate_button'=> 20,
				'product_info'        => 30,
				'description'         => 40,
				'sample_images'       => 50,
				'bottom_affiliate_button' => 60,
			),
			'product_info_fields' => array(
				'title'      => 1,
				'product_id' => 1,
				'content_id' => 1,
				'service'    => 1,
				'floor'      => 1,
				'category_name' => 1,
				'maker'      => 1,
				'label'      => 1,
				'director'   => 1,
				'genres'     => 1,
				'date'       => 1,
				'volume'     => 1,
				'price'      => 1,
				'list_price' => 1,
				'delivery_prices' => 1,
				'product_url' => 1,
				'affiliate_url' => 1,
			),
			'product_info_field_order' => array(
				'title'      => 10,
				'product_id' => 20,
				'content_id' => 30,
				'service'    => 40,
				'floor'      => 50,
				'category_name' => 60,
				'maker'      => 70,
				'label'      => 80,
				'director'   => 90,
				'genres'     => 100,
				'date'       => 110,
				'volume'     => 120,
				'price'      => 130,
				'list_price' => 140,
				'delivery_prices' => 150,
				'product_url' => 160,
				'affiliate_url' => 170,
			),
			'parent_category_id' => 0,
			'create_categories'  => 1,
			'create_parent_categories' => 0,
			'create_tags'        => 1,
			'term_slug_source'   => 'id',
			'category_iteminfo_keys' => array(
				'genre'    => 0,
				'maker'    => 0,
				'label'    => 1,
				'actress'  => 0,
				'director' => 0,
			),
			'tag_iteminfo_keys'  => array(
				'genre'    => 1,
				'maker'    => 0,
				'label'    => 0,
				'actress'  => 0,
				'director' => 0,
			),
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

		$settings = wp_parse_args( $saved, self::defaults() );
		$defaults = self::defaults();
		foreach ( array( 'body_sections', 'body_section_order', 'product_info_fields', 'product_info_field_order', 'category_iteminfo_keys', 'tag_iteminfo_keys' ) as $key ) {
			$settings[ $key ] = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? wp_parse_args( $settings[ $key ], $defaults[ $key ] ) : $defaults[ $key ];
		}

		return $settings;
	}

	public static function update( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$settings = wp_parse_args( $settings, self::get() );

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
		$settings['sync_post_date_with_release_date'] = ! empty( $input['sync_post_date_with_release_date'] ) ? 1 : 0;

		$movie_size = sanitize_key( $input['sample_movie_size'] ?? $settings['sample_movie_size'] );
		$settings['sample_movie_size'] = in_array( $movie_size, self::sample_movie_size_keys(), true ) ? $movie_size : 'size_720_480';

		$featured_source = sanitize_key( $input['featured_image_source'] ?? $settings['featured_image_source'] );
		$settings['featured_image_source'] = in_array( $featured_source, self::featured_image_source_keys(), true ) ? $featured_source : 'image_small';

		$sample_image_size = sanitize_key( $input['sample_image_size'] ?? $settings['sample_image_size'] );
		$settings['sample_image_size'] = in_array( $sample_image_size, self::sample_image_size_keys(), true ) ? $sample_image_size : 'sample_l';
		$settings['sample_image_max'] = max( 0, min( 100, absint( $input['sample_image_max'] ?? 0 ) ) );
		$settings['show_sample_image_continue_button'] = ! empty( $input['show_sample_image_continue_button'] ) ? 1 : 0;
		$settings['sample_image_continue_button_text'] = sanitize_text_field( $input['sample_image_continue_button_text'] ?? '続きを見る' );
		if ( '' === trim( $settings['sample_image_continue_button_text'] ) ) {
			$settings['sample_image_continue_button_text'] = '続きを見る';
		}

		$settings['title_template'] = sanitize_text_field( $input['title_template'] ?? $settings['title_template'] );
		if ( '' === trim( $settings['title_template'] ) ) {
			$settings['title_template'] = '{title}｜{label}';
		}

		$settings['body_sections'] = self::sanitize_boolean_map(
			$input['body_sections'] ?? array(),
			array_keys( $settings['body_sections'] )
		);
		$settings['body_section_order'] = self::sanitize_order_map(
			$input['body_section_order'] ?? $settings['body_section_order'],
			array_keys( $settings['body_section_order'] ),
			$settings['body_section_order']
		);
		$settings['product_info_fields'] = self::sanitize_boolean_map(
			$input['product_info_fields'] ?? array(),
			array_keys( $settings['product_info_fields'] )
		);
		$settings['product_info_field_order'] = self::sanitize_order_map(
			$input['product_info_field_order'] ?? $settings['product_info_field_order'],
			array_keys( $settings['product_info_field_order'] ),
			$settings['product_info_field_order']
		);

		$settings['parent_category_id'] = absint( $input['parent_category_id'] ?? 0 );
		$settings['create_categories']  = ! empty( $input['create_categories'] ) ? 1 : 0;
		$settings['create_parent_categories'] = ! empty( $input['create_parent_categories'] ) ? 1 : 0;
		$settings['create_tags']        = ! empty( $input['create_tags'] ) ? 1 : 0;
		$settings['term_slug_source']   = 'name' === sanitize_key( $input['term_slug_source'] ?? 'id' ) ? 'name' : 'id';
		$settings['category_iteminfo_keys'] = self::sanitize_iteminfo_keys( $input['category_iteminfo_keys'] ?? array() );
		$settings['tag_iteminfo_keys']      = self::sanitize_iteminfo_keys( $input['tag_iteminfo_keys'] ?? array() );
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

	private static function sanitize_iteminfo_keys( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		foreach ( self::taxonomy_iteminfo_options() as $key => $label ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		return $clean;
	}

	private static function sanitize_boolean_map( $input, $keys ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		foreach ( $keys as $key ) {
			$clean[ $key ] = ! empty( $input[ $key ] ) ? 1 : 0;
		}

		return $clean;
	}

	private static function sanitize_order_map( $input, $keys, $defaults ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();
		foreach ( $keys as $key ) {
			$value = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : absint( $defaults[ $key ] ?? 10 );
			$clean[ $key ] = max( 1, min( 999, $value ) );
		}

		return $clean;
	}

	public static function sample_movie_size_keys() {
		return array( 'size_720_480', 'size_644_414', 'size_560_360', 'size_476_306' );
	}

	public static function featured_image_source_keys() {
		return array( 'image_small', 'image_list', 'sample_image_1', 'sample_image_2', 'sample_image_3', 'sample_image_4', 'sample_image_5' );
	}

	public static function sample_image_size_keys() {
		return array( 'sample_l', 'sample_s' );
	}
}
