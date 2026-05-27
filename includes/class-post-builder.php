<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Post_Builder {
	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? wp_parse_args( $settings, YY_DMM_Auto_Post_Settings::defaults() ) : YY_DMM_Auto_Post_Settings::defaults();
	}

	public function build_content( $item, $description, $product_info_term_links = array() ) {
		$context = $this->build_context( $item, $description, $product_info_term_links );
		extract( $context, EXTR_SKIP );

		ob_start();
		include YY_DMM_AUTO_POST_DIR . 'templates/post-body.php';
		return (string) ob_get_clean();
	}

	public function get_wp_title( $item ) {
		$template = isset( $this->settings['title_template'] ) ? (string) $this->settings['title_template'] : '{title}｜{label}';
		$title = $this->replace_template_tokens( $template, $item );
		$title = trim( preg_replace( '/\s+/', ' ', $title ) );
		$title = trim( $title, " \t\n\r\0\x0B|-:：/｜" );

		return '' !== $title ? sanitize_text_field( $title ) : $this->get_base_title( $item );
	}

	public function get_content_id( $item ) {
		return isset( $item['content_id'] ) ? sanitize_key( (string) $item['content_id'] ) : '';
	}

	public static function title_template_token_options() {
		return array(
			'{title}'         => 'タイトル',
			'{date}'          => '発売日・配信日',
			'{volume}'        => '収録時間',
			'{price}'         => '価格',
			'{list_price}'    => '定価',
			'{delivery_prices}' => '配信価格',
			'{delivery_hd_price}' => 'HD配信価格',
			'{delivery_download_price}' => 'ダウンロード価格',
			'{delivery_stream_price}' => 'ストリーミング価格',
			'{delivery_iosdl_price}' => 'iOS DL価格',
			'{delivery_androiddl_price}' => 'Android DL価格',
			'{genres}'        => 'ジャンル名一覧',
			'{maker}'         => 'メーカー名',
			'{label}'         => 'レーベル名',
		);
	}

	private function build_context( $item, $description, $product_info_term_links = array() ) {
		$product_info_term_links = is_array( $product_info_term_links ) ? $product_info_term_links : array();
		$title            = $this->get_base_title( $item );
		$content_id       = $this->get_content_id( $item );
		$affiliate_url    = isset( $item['affiliateURL'] ) ? esc_url_raw( $item['affiliateURL'] ) : '';
		$sample_movie_url = $this->extract_sample_movie_url( $item );
		$sample_image_size = isset( $this->settings['sample_image_size'] ) ? sanitize_key( $this->settings['sample_image_size'] ) : 'sample_l';
		$sample_image_max = isset( $this->settings['sample_image_max'] ) ? absint( $this->settings['sample_image_max'] ) : 0;
		$sample_images    = self::extract_sample_image_urls( $item, $sample_image_size, $sample_image_max );
		$genres           = self::extract_iteminfo_names( $item, 'genre' );
		$makers           = self::extract_iteminfo_names( $item, 'maker' );
		$labels           = self::extract_iteminfo_names( $item, 'label' );
		$label_name       = $labels ? $labels[0] : '';
		$maker_name       = $makers ? $makers[0] : '';
		$date             = $this->format_date( $item['date'] ?? '' );
		$volume           = $this->format_volume( $item['volume'] ?? '' );
		$price            = $this->extract_price( $item );
		$list_price       = $this->extract_price( $item, 'list_price' );
		$delivery_prices  = $this->extract_delivery_prices( $item );
		$body_sections    = isset( $this->settings['body_sections'] ) && is_array( $this->settings['body_sections'] ) ? $this->settings['body_sections'] : array();
		$body_section_order = isset( $this->settings['body_section_order'] ) && is_array( $this->settings['body_section_order'] ) ? $this->settings['body_section_order'] : array();
		$product_info_fields = isset( $this->settings['product_info_fields'] ) && is_array( $this->settings['product_info_fields'] ) ? $this->settings['product_info_fields'] : array();
		$product_info_field_order = isset( $this->settings['product_info_field_order'] ) && is_array( $this->settings['product_info_field_order'] ) ? $this->settings['product_info_field_order'] : array();
		$defaults = YY_DMM_Auto_Post_Settings::defaults();

		return array(
			'title'             => $title,
			'content_id'        => $content_id,
			'product_id'        => isset( $item['product_id'] ) ? sanitize_text_field( (string) $item['product_id'] ) : '',
			'service_text'      => $this->format_name_code( $item['service_name'] ?? '', $item['service_code'] ?? '' ),
			'floor_text'        => $this->format_name_code( $item['floor_name'] ?? '', $item['floor_code'] ?? '' ),
			'category_name'     => isset( $item['category_name'] ) ? sanitize_text_field( (string) $item['category_name'] ) : '',
			'product_url'       => isset( $item['URL'] ) ? esc_url_raw( $item['URL'] ) : '',
			'affiliate_url'     => $affiliate_url,
			'sample_movie_url'  => $sample_movie_url,
			'sample_image_urls' => $sample_images,
			'show_sample_image_continue_button' => ! empty( $this->settings['show_sample_image_continue_button'] ) ? 1 : 0,
			'sample_image_continue_button_text' => sanitize_text_field( $this->settings['sample_image_continue_button_text'] ?? '' ),
			'description'       => (string) $description,
			'genres'            => $genres,
			'genre_items'       => self::build_linked_iteminfo_values( $genres, $product_info_term_links['genre'] ?? array() ),
			'maker_items'       => self::build_linked_iteminfo_values( $makers, $product_info_term_links['maker'] ?? array() ),
			'label_items'       => self::build_linked_iteminfo_values( $labels, $product_info_term_links['label'] ?? array() ),
			'label_name'        => $label_name,
			'maker_name'        => $maker_name,
			'date'              => $date,
			'volume'            => $volume,
			'price'             => $price,
			'list_price'        => $list_price,
			'delivery_prices'   => $delivery_prices,
			'body_sections'     => wp_parse_args( $body_sections, $defaults['body_sections'] ),
			'body_section_order' => wp_parse_args( $body_section_order, $defaults['body_section_order'] ),
			'product_info_fields' => wp_parse_args( $product_info_fields, $defaults['product_info_fields'] ),
			'product_info_field_order' => wp_parse_args( $product_info_field_order, $defaults['product_info_field_order'] ),
			'item'              => is_array( $item ) ? $item : array(),
		);
	}

	private function replace_template_tokens( $template, $item ) {
		$genres = self::extract_iteminfo_names( $item, 'genre' );
		$makers = self::extract_iteminfo_names( $item, 'maker' );
		$labels = self::extract_iteminfo_names( $item, 'label' );
		$delivery_prices = $this->extract_delivery_prices( $item );
		$replacements = array(
			'{title}'         => $this->get_base_title( $item ),
			'{date}'          => $this->format_date( $item['date'] ?? '' ),
			'{volume}'        => $this->format_volume( $item['volume'] ?? '' ),
			'{price}'         => $this->extract_price( $item ),
			'{list_price}'    => $this->extract_price( $item, 'list_price' ),
			'{delivery_prices}' => implode( ' / ', $delivery_prices ),
			'{delivery_hd_price}' => $this->extract_delivery_price( $item, 'hd' ),
			'{delivery_download_price}' => $this->extract_delivery_price( $item, 'download' ),
			'{delivery_stream_price}' => $this->extract_delivery_price( $item, 'stream' ),
			'{delivery_iosdl_price}' => $this->extract_delivery_price( $item, 'iosdl' ),
			'{delivery_androiddl_price}' => $this->extract_delivery_price( $item, 'androiddl' ),
			'{genres}'        => implode( ', ', $genres ),
			'{maker}'         => $makers ? $makers[0] : '',
			'{label}'         => $labels ? $labels[0] : '',
		);

		return strtr( (string) $template, $replacements );
	}

	private function get_base_title( $item ) {
		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		if ( '' !== $title ) {
			return $title;
		}

		$content_id = $this->get_content_id( $item );
		return '' !== $content_id ? $content_id : 'No Title';
	}

	private function extract_sample_movie_url( $item ) {
		$movie_info = isset( $item['sampleMovieURL'] ) && is_array( $item['sampleMovieURL'] ) ? $item['sampleMovieURL'] : array();
		$preferred_key = isset( $this->settings['sample_movie_size'] ) ? sanitize_key( $this->settings['sample_movie_size'] ) : 'size_720_480';
		$keys = array_values( array_unique( array_merge( array( $preferred_key ), array( 'size_720_480', 'size_644_414', 'size_560_360', 'size_476_306' ) ) ) );
		foreach ( $keys as $key ) {
			if ( ! empty( $movie_info[ $key ] ) && is_string( $movie_info[ $key ] ) ) {
				return esc_url_raw( $movie_info[ $key ] );
			}
		}

		return '';
	}

	public static function extract_sample_image_urls( $item, $preferred_group = '', $max = 0 ) {
		$sample = isset( $item['sampleImageURL'] ) && is_array( $item['sampleImageURL'] ) ? $item['sampleImageURL'] : array();
		$groups = array( 'sample_l', 'sample_s' );
		$preferred_group = sanitize_key( (string) $preferred_group );
		if ( in_array( $preferred_group, $groups, true ) ) {
			$groups = array_values( array_unique( array_merge( array( $preferred_group ), $groups ) ) );
		}
		$max = absint( $max );

		foreach ( $groups as $group ) {
			$clean = self::extract_sample_image_group_urls( $sample, $group );
			if ( $clean ) {
				return $max > 0 ? array_slice( $clean, 0, $max ) : $clean;
			}
		}

		return array();
	}

	private static function extract_sample_image_group_urls( $sample, $group ) {
		if ( empty( $sample[ $group ] ) ) {
			return array();
		}

		$group_data = $sample[ $group ];
		$images = is_array( $group_data ) && array_key_exists( 'image', $group_data ) ? $group_data['image'] : $group_data;
		if ( is_string( $images ) ) {
			$images = array( $images );
		}

		if ( ! is_array( $images ) ) {
			return array();
		}

		$clean = array();
		array_walk_recursive(
			$images,
			static function ( $url ) use ( &$clean ) {
				if ( is_string( $url ) && '' !== trim( $url ) ) {
					$clean[] = esc_url_raw( $url );
				}
			}
		);

		return array_values( array_unique( $clean ) );
	}

	public static function extract_iteminfo_names( $item, $key ) {
		$entries = self::extract_iteminfo_entries( $item, $key );
		$names   = array();
		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['name'] ) ) {
				$names[] = sanitize_text_field( $entry['name'] );
			}
		}

		return array_values( array_unique( $names ) );
	}

	public static function extract_first_iteminfo_name( $item, $key ) {
		$names = self::extract_iteminfo_names( $item, $key );
		return $names ? $names[0] : '';
	}

	private static function build_linked_iteminfo_values( $names, $term_links ) {
		$term_links = is_array( $term_links ) ? $term_links : array();
		$urls_by_name = array();
		foreach ( $term_links as $term_link ) {
			if ( ! is_array( $term_link ) || empty( $term_link['name'] ) ) {
				continue;
			}

			$urls_by_name[ sanitize_text_field( $term_link['name'] ) ] = ! empty( $term_link['url'] ) ? esc_url_raw( $term_link['url'] ) : '';
		}

		$items = array();
		foreach ( $names as $name ) {
			$name = sanitize_text_field( $name );
			if ( '' === $name ) {
				continue;
			}

			$items[] = array(
				'name' => $name,
				'url'  => $urls_by_name[ $name ] ?? '',
			);
		}

		return $items;
	}

	public static function extract_iteminfo_entries( $item, $key ) {
		if ( empty( $item['iteminfo'] ) || ! is_array( $item['iteminfo'] ) ) {
			return array();
		}

		$value = $item['iteminfo'][ $key ] ?? array();
		if ( isset( $value['name'] ) ) {
			$value = array( $value );
		}

		return is_array( $value ) ? array_filter( $value, 'is_array' ) : array();
	}

	private function format_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})/', $value, $matches ) ) {
			return sprintf( '%d年%d月%d日', $matches[1], $matches[2], $matches[3] );
		}

		return sanitize_text_field( $value );
	}

	private function format_volume( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		if ( ctype_digit( $value ) ) {
			$total = absint( $value );
			$hours = intdiv( $total, 60 );
			$minutes = $total % 60;
			return $this->format_duration_parts( $hours, $minutes );
		}

		if ( preg_match( '/^(\d+):(\d{1,2}):(\d{1,2})$/', $value, $matches ) ) {
			$hours = absint( $matches[1] );
			$minutes = absint( $matches[2] );
			$seconds = absint( $matches[3] );
			return $this->format_duration_parts( $hours, $minutes, $seconds );
		}

		if ( preg_match( '/^(\d+):(\d+)$/', $value, $matches ) ) {
			$hours = absint( $matches[1] );
			$minutes = absint( $matches[2] );
			return $this->format_duration_parts( $hours, $minutes );
		}

		return sanitize_text_field( $value );
	}

	private function format_duration_parts( $hours, $minutes, $seconds = 0 ) {
		$hours = absint( $hours );
		$minutes = absint( $minutes );
		$seconds = absint( $seconds );

		if ( $hours > 0 ) {
			return $minutes > 0 ? sprintf( '%d時間%d分', $hours, $minutes ) : sprintf( '%d時間', $hours );
		}

		if ( $minutes > 0 ) {
			return $seconds > 0 ? sprintf( '%d分%d秒', $minutes, $seconds ) : sprintf( '%d分', $minutes );
		}

		return $seconds > 0 ? sprintf( '%d秒', $seconds ) : '0分';
	}

	private function extract_price( $item, $preferred_key = 'price' ) {
		$prices = isset( $item['prices'] ) && is_array( $item['prices'] ) ? $item['prices'] : array();
		foreach ( array( $preferred_key, 'price', 'list_price' ) as $key ) {
			if ( ! empty( $prices[ $key ] ) && is_scalar( $prices[ $key ] ) ) {
				return $this->format_price( $prices[ $key ] );
			}
		}

		return '';
	}

	private function extract_delivery_prices( $item ) {
		$deliveries = $item['prices']['deliveries']['delivery'] ?? array();
		if ( isset( $deliveries['type'] ) ) {
			$deliveries = array( $deliveries );
		}

		if ( ! is_array( $deliveries ) ) {
			return array();
		}

		$items = array();
		foreach ( $deliveries as $delivery ) {
			if ( ! is_array( $delivery ) ) {
				continue;
			}

			$type = isset( $delivery['type'] ) ? sanitize_text_field( (string) $delivery['type'] ) : '';
			$price = isset( $delivery['price'] ) ? $this->format_price( $delivery['price'] ) : '';
			$list_price = isset( $delivery['list_price'] ) ? $this->format_price( $delivery['list_price'] ) : '';
			if ( '' === $type && '' === $price && '' === $list_price ) {
				continue;
			}

			$text = '' !== $type ? $type : 'delivery';
			if ( '' !== $price ) {
				$text .= ': ' . $price;
			}
			if ( '' !== $list_price && $list_price !== $price ) {
				$text .= ' (定価 ' . $list_price . ')';
			}
			$items[] = $text;
		}

		return $items;
	}

	private function extract_delivery_price( $item, $type, $preferred_key = 'price' ) {
		$deliveries = $item['prices']['deliveries']['delivery'] ?? array();
		if ( isset( $deliveries['type'] ) ) {
			$deliveries = array( $deliveries );
		}

		if ( ! is_array( $deliveries ) ) {
			return '';
		}

		foreach ( $deliveries as $delivery ) {
			if ( ! is_array( $delivery ) ) {
				continue;
			}

			$delivery_type = isset( $delivery['type'] ) ? sanitize_key( (string) $delivery['type'] ) : '';
			if ( $type !== $delivery_type || ! array_key_exists( $preferred_key, $delivery ) || ! is_scalar( $delivery[ $preferred_key ] ) ) {
				continue;
			}

			return $this->format_price( $delivery[ $preferred_key ] );
		}

		return '';
	}

	private function format_name_code( $name, $code ) {
		$name = sanitize_text_field( (string) $name );
		$code = sanitize_key( (string) $code );
		if ( '' !== $name && '' !== $code ) {
			return sprintf( '%s (%s)', $name, $code );
		}

		return '' !== $name ? $name : $code;
	}

	private function format_price( $value ) {
		$value = sanitize_text_field( (string) $value );
		$value = trim( str_replace( '~', '〜', $value ) );
		if ( '' === $value || false !== strpos( $value, '円' ) ) {
			return $value;
		}

		if ( preg_match( '/^([0-9][0-9,]*)(〜)?$/', $value, $matches ) ) {
			return $matches[1] . '円' . ( ! empty( $matches[2] ) ? '〜' : '' );
		}

		return $value;
	}
}
