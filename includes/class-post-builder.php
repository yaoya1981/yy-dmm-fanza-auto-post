<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Post_Builder {
	public function build_content( $item, $description ) {
		$context = $this->build_context( $item, $description );
		extract( $context, EXTR_SKIP );

		ob_start();
		include YY_DMM_AUTO_POST_DIR . 'templates/post-body.php';
		return (string) ob_get_clean();
	}

	public function get_wp_title( $item ) {
		$title = $this->get_base_title( $item );
		$label = self::extract_first_iteminfo_name( $item, 'label' );

		return $label ? sprintf( '%s｜%s', $title, $label ) : $title;
	}

	public function get_content_id( $item ) {
		return isset( $item['content_id'] ) ? sanitize_key( (string) $item['content_id'] ) : '';
	}

	private function build_context( $item, $description ) {
		$title            = $this->get_base_title( $item );
		$content_id       = $this->get_content_id( $item );
		$affiliate_url    = isset( $item['affiliateURL'] ) ? esc_url_raw( $item['affiliateURL'] ) : '';
		$sample_movie_url = $this->extract_sample_movie_url( $item );
		$sample_images    = self::extract_sample_image_urls( $item );
		$genres           = self::extract_iteminfo_names( $item, 'genre' );
		$label_name       = self::extract_first_iteminfo_name( $item, 'label' );
		$date             = $this->format_date( $item['date'] ?? '' );
		$volume           = $this->format_volume( $item['volume'] ?? '' );
		$price            = $this->extract_price( $item );

		return array(
			'title'             => $title,
			'content_id'        => $content_id,
			'affiliate_url'     => $affiliate_url,
			'sample_movie_url'  => $sample_movie_url,
			'sample_image_urls' => $sample_images,
			'description'       => (string) $description,
			'genres'            => $genres,
			'label_name'        => $label_name,
			'date'              => $date,
			'volume'            => $volume,
			'price'             => $price,
			'item'              => is_array( $item ) ? $item : array(),
		);
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
		foreach ( array( 'size_720_480', 'size_644_414', 'size_560_360', 'size_476_306' ) as $key ) {
			if ( ! empty( $movie_info[ $key ] ) && is_string( $movie_info[ $key ] ) ) {
				return esc_url_raw( $movie_info[ $key ] );
			}
		}

		return '';
	}

	public static function extract_sample_image_urls( $item ) {
		$sample = isset( $item['sampleImageURL'] ) && is_array( $item['sampleImageURL'] ) ? $item['sampleImageURL'] : array();
		foreach ( array( 'sample_l', 'sample_s' ) as $group ) {
			if ( empty( $sample[ $group ] ) || ! is_array( $sample[ $group ] ) ) {
				continue;
			}

			$images = $sample[ $group ]['image'] ?? array();
			if ( is_string( $images ) ) {
				$images = array( $images );
			}

			if ( is_array( $images ) && ! empty( $images ) ) {
				$clean = array();
				foreach ( $images as $url ) {
					if ( is_string( $url ) && '' !== trim( $url ) ) {
						$clean[] = esc_url_raw( $url );
					}
				}

				if ( $clean ) {
					return $clean;
				}
			}
		}

		return array();
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
			return $hours > 0 ? sprintf( '%d時間%d分', $hours, $minutes ) : sprintf( '%d分', $minutes );
		}

		if ( preg_match( '/^(\d+):(\d+)$/', $value, $matches ) ) {
			$hours = absint( $matches[1] );
			$minutes = absint( $matches[2] );
			return $hours > 0 ? sprintf( '%d時間%d分', $hours, $minutes ) : sprintf( '%d分', $minutes );
		}

		return sanitize_text_field( $value );
	}

	private function extract_price( $item ) {
		$prices = isset( $item['prices'] ) && is_array( $item['prices'] ) ? $item['prices'] : array();
		foreach ( array( 'price', 'list_price', 'deliveries' ) as $key ) {
			if ( ! empty( $prices[ $key ] ) && is_scalar( $prices[ $key ] ) ) {
				return sanitize_text_field( (string) $prices[ $key ] );
			}
		}

		return '';
	}
}
