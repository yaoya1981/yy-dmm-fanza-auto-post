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
		);
	}

	public static function defaults() {
		return array(
			'api_id'             => '',
			'affiliate_id'       => '',
			'post_affiliate_id'  => '',
			'site'               => 'FANZA',
			'service'            => 'digital',
			'floor'              => 'videoc',
			'keyword'            => '',
			'sort'               => 'date',
			'hits'               => 50,
			'post_status'        => 'draft',
			'sync_post_date_with_release_date' => 0,
			'fetch_description'  => 1,
			'sample_movie_size'  => 'size_720_480',
			'save_featured_image' => 1,
			'featured_image_source' => 'image_small',
			'sample_image_size'  => 'sample_l',
			'sample_image_max'   => 0,
			'show_sample_image_continue_button' => 0,
			'sample_image_continue_button_text' => '続きを見る',
			'title_template'     => '{title}｜{label}',
			'affiliate_button_text_top' => '公式ページを見る',
			'affiliate_button_text_middle' => '公式ページを見る',
			'affiliate_button_text_bottom' => '公式ページを見る',
			'body_sections'      => array(
				'sample_movie'        => 1,
				'top_affiliate_button'=> 1,
				'product_info'        => 1,
				'description'         => 1,
				'middle_affiliate_button' => 1,
				'sample_images'       => 1,
				'bottom_affiliate_button' => 1,
			),
			'body_section_order' => array(
				'sample_movie'        => 10,
				'top_affiliate_button'=> 20,
				'product_info'        => 30,
				'description'         => 40,
				'middle_affiliate_button' => 50,
				'sample_images'       => 60,
				'bottom_affiliate_button' => 70,
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
				'genres'     => 1,
				'date'       => 1,
				'volume'     => 1,
				'price'      => 1,
				'list_price' => 1,
				'delivery_prices' => 1,
				'product_url' => 1,
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
				'genres'     => 90,
				'date'       => 100,
				'volume'     => 110,
				'price'      => 120,
				'list_price' => 130,
				'delivery_prices' => 140,
				'product_url' => 150,
			),
			'product_info_link_terms' => 1,
			'product_info_product_url_label' => '商品ページ',
			'product_info_product_url_link_text' => '商品ページを見る',
			'product_info_product_url_button' => 0,
			'parent_category_id' => 0,
			'create_categories'  => 1,
			'create_parent_categories' => 0,
			'category_parent_iteminfo_keys' => array(
				'genre' => 0,
				'maker' => 0,
				'label' => 0,
			),
			'create_tags'        => 1,
			'term_slug_source'   => 'id',
			'category_child_slug_source' => 'id',
			'category_iteminfo_slug_sources' => array(
				'genre' => 'id',
				'maker' => 'id',
				'label' => 'id',
			),
			'tag_iteminfo_slug_sources' => array(
				'genre' => 'id',
				'maker' => 'id',
				'label' => 'id',
			),
			'category_iteminfo_keys' => array(
				'genre'    => 0,
				'maker'    => 0,
				'label'    => 1,
			),
			'tag_iteminfo_keys'  => array(
				'genre'    => 1,
				'maker'    => 0,
				'label'    => 0,
			),
			'max_posts'          => 1,
			'max_posts_all'      => 0,
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
		if ( ! array_key_exists( 'category_child_slug_source', $saved ) ) {
			$settings['category_child_slug_source'] = 'name' === ( $settings['term_slug_source'] ?? 'id' ) ? 'name' : 'id';
		}
		if ( ! array_key_exists( 'category_iteminfo_slug_sources', $saved ) ) {
			$settings['category_iteminfo_slug_sources'] = self::default_iteminfo_slug_sources( $settings['category_child_slug_source'] );
		}
		if ( ! array_key_exists( 'tag_iteminfo_slug_sources', $saved ) ) {
			$settings['tag_iteminfo_slug_sources'] = self::default_iteminfo_slug_sources( $settings['term_slug_source'] );
		}
		if ( ! array_key_exists( 'category_parent_iteminfo_keys', $saved ) ) {
			$settings['category_parent_iteminfo_keys'] = self::default_iteminfo_keys( ! empty( $settings['create_parent_categories'] ) );
		}

		$defaults = self::defaults();
		foreach ( array( 'body_sections', 'product_info_fields', 'category_iteminfo_keys', 'tag_iteminfo_keys', 'category_parent_iteminfo_keys' ) as $key ) {
			$settings[ $key ] = isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ? wp_parse_args( $settings[ $key ], $defaults[ $key ] ) : $defaults[ $key ];
		}
		$settings['body_section_order'] = self::sanitize_order_map( $saved['body_section_order'] ?? array(), array_keys( $defaults['body_section_order'] ), $defaults['body_section_order'] );
		$settings['product_info_fields'] = self::sanitize_boolean_map( $settings['product_info_fields'], array_keys( $defaults['product_info_fields'] ) );
		$settings['product_info_field_order'] = self::sanitize_order_map( $saved['product_info_field_order'] ?? array(), array_keys( $defaults['product_info_field_order'] ), $defaults['product_info_field_order'] );
		$settings['product_info_link_terms'] = ! empty( $settings['product_info_link_terms'] ) ? 1 : 0;
		$settings['category_parent_iteminfo_keys'] = self::sanitize_iteminfo_keys( $settings['category_parent_iteminfo_keys'] );
		$settings['category_iteminfo_slug_sources'] = self::sanitize_slug_source_map( $settings['category_iteminfo_slug_sources'] ?? array(), $settings['category_child_slug_source'] );
		$settings['tag_iteminfo_slug_sources'] = self::sanitize_slug_source_map( $settings['tag_iteminfo_slug_sources'] ?? array(), $settings['term_slug_source'] );

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
		$settings['post_affiliate_id'] = sanitize_text_field( $input['post_affiliate_id'] ?? '' );
		$settings['site']         = sanitize_text_field( $input['site'] ?? 'FANZA' );
		$settings['service']      = sanitize_key( $input['service'] ?? 'digital' );
		$settings['floor']        = sanitize_key( $input['floor'] ?? 'videoc' );
		$settings['keyword']      = sanitize_text_field( $input['keyword'] ?? '' );
		$settings['sort']         = sanitize_key( $input['sort'] ?? 'date' );
		$settings['hits']         = max( 1, min( 100, absint( $input['hits'] ?? 50 ) ) );

		$post_status = sanitize_key( $input['post_status'] ?? 'draft' );
		$settings['post_status'] = in_array( $post_status, array( 'draft', 'publish' ), true ) ? $post_status : 'draft';
		$settings['sync_post_date_with_release_date'] = ! empty( $input['sync_post_date_with_release_date'] ) ? 1 : 0;
		$settings['fetch_description'] = ! empty( $input['fetch_description'] ) ? 1 : 0;

		$movie_size = sanitize_key( $input['sample_movie_size'] ?? $settings['sample_movie_size'] );
		$settings['sample_movie_size'] = in_array( $movie_size, self::sample_movie_size_keys(), true ) ? $movie_size : 'size_720_480';

		$settings['save_featured_image'] = ! empty( $input['save_featured_image'] ) ? 1 : 0;
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
		foreach ( array( 'top', 'middle', 'bottom' ) as $position ) {
			$key = 'affiliate_button_text_' . $position;
			$settings[ $key ] = sanitize_text_field( $input[ $key ] ?? $settings[ $key ] );
			if ( '' === trim( $settings[ $key ] ) ) {
				$settings[ $key ] = '公式ページを見る';
			}
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
		$settings['product_info_link_terms'] = ! empty( $input['product_info_link_terms'] ) ? 1 : 0;
		$settings['product_info_product_url_label'] = sanitize_text_field( $input['product_info_product_url_label'] ?? '商品ページ' );
		if ( '' === trim( $settings['product_info_product_url_label'] ) ) {
			$settings['product_info_product_url_label'] = '商品ページ';
		}
		$settings['product_info_product_url_link_text'] = sanitize_text_field( $input['product_info_product_url_link_text'] ?? '商品ページを見る' );
		if ( '' === trim( $settings['product_info_product_url_link_text'] ) ) {
			$settings['product_info_product_url_link_text'] = '商品ページを見る';
		}
		$settings['product_info_product_url_button'] = ! empty( $input['product_info_product_url_button'] ) ? 1 : 0;

		$settings['parent_category_id'] = absint( $input['parent_category_id'] ?? 0 );
		$settings['create_categories']  = ! empty( $input['create_categories'] ) ? 1 : 0;
		$legacy_create_parent_categories = ! empty( $input['create_parent_categories'] ) ? 1 : 0;
		$settings['category_parent_iteminfo_keys'] = self::sanitize_iteminfo_keys(
			$input['category_parent_iteminfo_keys'] ?? self::default_iteminfo_keys( $legacy_create_parent_categories )
		);
		$settings['create_parent_categories'] = in_array( 1, $settings['category_parent_iteminfo_keys'], true ) ? 1 : 0;
		$settings['create_tags']        = ! empty( $input['create_tags'] ) ? 1 : 0;
		$settings['term_slug_source']   = 'name' === sanitize_key( $input['term_slug_source'] ?? 'id' ) ? 'name' : 'id';
		$category_child_slug_source = sanitize_key( $input['category_child_slug_source'] ?? $settings['term_slug_source'] );
		$settings['category_child_slug_source'] = 'name' === $category_child_slug_source ? 'name' : 'id';
		$settings['category_iteminfo_slug_sources'] = self::sanitize_slug_source_map(
			$input['category_iteminfo_slug_sources'] ?? array(),
			$settings['category_child_slug_source']
		);
		$settings['tag_iteminfo_slug_sources'] = self::sanitize_slug_source_map(
			$input['tag_iteminfo_slug_sources'] ?? array(),
			$settings['term_slug_source']
		);
		$settings['category_iteminfo_keys'] = self::sanitize_iteminfo_keys( $input['category_iteminfo_keys'] ?? array() );
		$settings['tag_iteminfo_keys']      = self::sanitize_iteminfo_keys( $input['tag_iteminfo_keys'] ?? array() );
		$settings['max_posts']          = max( 1, min( 50, absint( $input['max_posts'] ?? 1 ) ) );
		$settings['max_posts_all']      = ! empty( $input['max_posts_all'] ) ? 1 : 0;
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

	private static function default_iteminfo_keys( $enabled ) {
		$defaults = array();
		foreach ( self::taxonomy_iteminfo_options() as $key => $label ) {
			$defaults[ $key ] = ! empty( $enabled ) ? 1 : 0;
		}

		return $defaults;
	}

	private static function default_iteminfo_slug_sources( $source ) {
		$source = 'name' === $source ? 'name' : 'id';
		$defaults = array();
		foreach ( self::taxonomy_iteminfo_options() as $key => $label ) {
			$defaults[ $key ] = $source;
		}

		return $defaults;
	}

	private static function sanitize_slug_source_map( $input, $default = 'id' ) {
		$input = is_array( $input ) ? $input : array();
		$default = 'name' === $default ? 'name' : 'id';
		$clean = array();
		foreach ( self::taxonomy_iteminfo_options() as $key => $label ) {
			$value = sanitize_key( $input[ $key ] ?? $default );
			$clean[ $key ] = 'name' === $value ? 'name' : 'id';
		}

		return $clean;
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
		$orders = array();
		$input_values = array();
		foreach ( $keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$input_values[] = absint( $input[ $key ] );
			}
		}
		$use_legacy_scale = $input_values && max( $input_values ) > count( $keys );

		foreach ( $keys as $key ) {
			$value = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : absint( $defaults[ $key ] ?? 1 );
			if ( ! isset( $input[ $key ] ) && $use_legacy_scale ) {
				$value *= 10;
			}
			$orders[ $key ] = max( 1, min( 999, $value ) );
		}

		$sorted_keys = $keys;
		usort(
			$sorted_keys,
			static function ( $a, $b ) use ( $orders ) {
				if ( $orders[ $a ] === $orders[ $b ] ) {
					return strcmp( $a, $b );
				}

				return $orders[ $a ] <=> $orders[ $b ];
			}
		);

		$clean = array();
		foreach ( $sorted_keys as $index => $key ) {
			$clean[ $key ] = ( $index + 1 ) * 10;
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
