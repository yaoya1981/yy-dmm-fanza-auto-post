<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Media {
	public function get_or_sideload_featured( $item ) {
		$content_id = $this->get_content_id( $item );
		if ( '' === $content_id ) {
			return new WP_Error( 'yy_dmm_media_missing_content_id', 'content_idがないためアイキャッチ画像を処理できません。' );
		}

		$existing_id = $this->find_existing_attachment_id( $content_id );
		if ( $existing_id ) {
			return $existing_id;
		}

		$url = $this->choose_featured_url( $item );
		if ( '' === $url ) {
			return new WP_Error( 'yy_dmm_media_no_image', sprintf( '%s: アイキャッチ候補画像がありません。', $content_id ) );
		}

		return $this->sideload( $url, $item, $content_id );
	}

	private function find_existing_attachment_id( $content_id ) {
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_yy_dmm_content_id',
				'meta_value'     => $content_id,
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

		foreach ( array( 'large', 'list', 'small' ) as $key ) {
			if ( ! empty( $image_url[ $key ] ) && is_string( $image_url[ $key ] ) ) {
				return esc_url_raw( $image_url[ $key ] );
			}
		}

		$sample_images = YY_DMM_Auto_Post_Post_Builder::extract_sample_image_urls( $item );
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
