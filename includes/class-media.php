<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Media {
	private $settings;

	public function __construct( $settings = array() ) {
		$this->settings = is_array( $settings ) ? wp_parse_args( $settings, YY_DMM_Auto_Post_Settings::defaults() ) : YY_DMM_Auto_Post_Settings::defaults();
	}

	public function get_or_sideload_featured( $item ) {
		$content_id = $this->get_content_id( $item );
		if ( '' === $content_id ) {
			return new WP_Error( 'yy_dmm_media_missing_content_id', 'content_idがないためアイキャッチ画像を処理できません。' );
		}

		$url = $this->choose_featured_url( $item );
		if ( '' === $url ) {
			return new WP_Error( 'yy_dmm_media_no_image', sprintf( '%s: アイキャッチ候補画像がありません。', $content_id ) );
		}

		$existing_id = $this->find_existing_attachment_id( $content_id, $url );
		if ( $existing_id ) {
			return $existing_id;
		}

		return $this->sideload( $url, $item, $content_id );
	}

	private function find_existing_attachment_id( $content_id, $url ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => '_yy_dmm_content_id',
						'value' => $content_id,
					),
					array(
						'key'   => '_yy_dmm_featured_image_url',
						'value' => esc_url_raw( $url ),
					),
				),
			)
		);

		return ! empty( $attachments ) ? absint( $attachments[0] ) : 0;
	}

	private function sideload( $url, $item, $content_id ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$path = wp_parse_url( $url, PHP_URL_PATH );
		$name = $path ? wp_basename( $path ) : '';
		if ( '' === $name || ! preg_match( '/\.[a-z0-9]{2,5}$/i', $name ) ) {
			$name = $content_id . '.jpg';
		}

		$file = array(
			'name'     => sanitize_file_name( $name ),
			'tmp_name' => $tmp,
		);

		$title = $this->get_title( $item );
		$id = media_handle_sideload( $file, 0, $title );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return $id;
		}

		update_post_meta( $id, '_yy_dmm_content_id', $content_id );
		update_post_meta( $id, '_yy_dmm_featured_image_url', esc_url_raw( $url ) );
		update_post_meta( $id, '_wp_attachment_image_alt', $title );

		return absint( $id );
	}

	private function choose_featured_url( $item ) {
		$image_url = isset( $item['imageURL'] ) && is_array( $item['imageURL'] ) ? $item['imageURL'] : array();
		$source = isset( $this->settings['featured_image_source'] ) ? sanitize_key( $this->settings['featured_image_source'] ) : 'image_small';
		if ( 'auto' === $source ) {
			$source = 'image_small';
		}

		if ( 0 === strpos( $source, 'image_' ) ) {
			$key = substr( $source, 6 );
			return ! empty( $image_url[ $key ] ) && is_string( $image_url[ $key ] ) ? esc_url_raw( $image_url[ $key ] ) : '';
		}

		if ( 'sample_l' === $source ) {
			$source = 'sample_image_1';
		}

		if ( 0 === strpos( $source, 'sample_image_' ) ) {
			$index = absint( substr( $source, 13 ) );
			return $this->choose_sample_featured_url( $item, $index );
		}

		foreach ( array( 'list', 'small' ) as $key ) {
			if ( ! empty( $image_url[ $key ] ) && is_string( $image_url[ $key ] ) ) {
				return esc_url_raw( $image_url[ $key ] );
			}
		}

		return $this->choose_sample_featured_url( $item, 1 );
	}

	private function choose_sample_featured_url( $item, $index ) {
		$sample_images = YY_DMM_Auto_Post_Post_Builder::extract_sample_image_urls( $item, 'sample_l', 0 );
		if ( empty( $sample_images ) ) {
			return '';
		}

		$index = max( 1, min( 5, absint( $index ) ) );
		$offset = $index - 1;
		if ( ! empty( $sample_images[ $offset ] ) ) {
			return esc_url_raw( $sample_images[ $offset ] );
		}

		return ! empty( $sample_images[0] ) ? esc_url_raw( $sample_images[0] ) : '';
	}

	private function get_title( $item ) {
		$title = isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '';
		return '' !== $title ? $title : $this->get_content_id( $item );
	}

	private function get_content_id( $item ) {
		return isset( $item['content_id'] ) ? sanitize_key( (string) $item['content_id'] ) : '';
	}
}
