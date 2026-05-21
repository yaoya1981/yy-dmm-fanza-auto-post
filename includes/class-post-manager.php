<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class YY_DMM_Auto_Post_Post_Manager {
	private $settings;
	private $builder;

	public function __construct( $settings, YY_DMM_Auto_Post_Post_Builder $builder ) {
		$this->settings = $settings;
		$this->builder  = $builder;
	}

	public function is_posted( $content_id ) {
		return (bool) $this->find_existing_post_id( $content_id );
	}

	public function create_post( $item, $content, $featured_media_id ) {
		$content_id = $this->builder->get_content_id( $item );
		if ( '' === $content_id ) {
			return new WP_Error( 'yy_dmm_post_missing_content_id', 'content_idがないため投稿できません。' );
		}

		$post_type = post_type_exists( $this->settings['post_type'] ) ? $this->settings['post_type'] : 'post';
		$post_id = wp_insert_post(
			array(
				'post_title'   => $this->builder->get_wp_title( $item ),
				'post_content' => $content,
				'post_status'  => $this->settings['post_status'],
				'post_type'    => $post_type,
				'post_name'    => sanitize_title( $content_id ),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_yy_dmm_content_id', $content_id );
		update_post_meta( $post_id, '_yy_dmm_affiliate_url', esc_url_raw( $item['affiliateURL'] ?? '' ) );
		update_post_meta( $post_id, '_yy_dmm_product_url', esc_url_raw( $item['URL'] ?? '' ) );

		if ( $featured_media_id ) {
			set_post_thumbnail( $post_id, absint( $featured_media_id ) );
		}

		$this->assign_categories( $post_id, $item );
		$this->assign_tags( $post_id, $item );

		return absint( $post_id );
	}

	private function find_existing_post_id( $content_id ) {
		$content_id = sanitize_key( (string) $content_id );
		if ( '' === $content_id ) {
			return 0;
		}

		$post_type = post_type_exists( $this->settings['post_type'] ) ? $this->settings['post_type'] : 'post';
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_yy_dmm_content_id',
				'meta_value'     => $content_id,
			)
		);

		if ( ! empty( $posts ) ) {
			return absint( $posts[0] );
		}

		$posts = get_posts(
			array(
				'name'           => sanitize_title( $content_id ),
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		return ! empty( $posts ) ? absint( $posts[0] ) : 0;
	}

	private function assign_categories( $post_id, $item ) {
		if ( ! taxonomy_exists( 'category' ) ) {
			return;
		}

		$ids = array();
		$entries = YY_DMM_Auto_Post_Post_Builder::extract_iteminfo_entries( $item, 'label' );
		foreach ( $entries as $entry ) {
			$name = isset( $entry['name'] ) ? sanitize_text_field( $entry['name'] ) : '';
			if ( '' === $name ) {
				continue;
			}

			$slug = ! empty( $entry['id'] ) ? sanitize_title( (string) $entry['id'] ) : sanitize_title( $name );
			$term_id = $this->get_or_create_category( $name, $slug );
			if ( $term_id ) {
				$ids[] = $term_id;
			}
		}

		if ( $ids ) {
			wp_set_post_terms( $post_id, array_values( array_unique( $ids ) ), 'category' );
		}
	}

	private function get_or_create_category( $name, $slug ) {
		$parent = absint( $this->settings['parent_category_id'] );
		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term && ( 0 === $parent || absint( $term->parent ) === $parent ) ) {
			return absint( $term->term_id );
		}

		$term = get_term_by( 'name', $name, 'category' );
		if ( $term && ( 0 === $parent || absint( $term->parent ) === $parent ) ) {
			return absint( $term->term_id );
		}

		if ( empty( $this->settings['create_categories'] ) ) {
			return 0;
		}

		$result = wp_insert_term(
			$name,
			'category',
			array(
				'slug'   => $slug,
				'parent' => $parent,
			)
		);

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['term_id'] ) ) {
				return absint( $data['term_id'] );
			}

			return is_numeric( $data ) ? absint( $data ) : 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}

	private function assign_tags( $post_id, $item ) {
		if ( ! taxonomy_exists( 'post_tag' ) ) {
			return;
		}

		$ids = array();
		$genres = YY_DMM_Auto_Post_Post_Builder::extract_iteminfo_names( $item, 'genre' );
		foreach ( $genres as $genre ) {
			$term_id = $this->get_or_create_tag( $genre );
			if ( $term_id ) {
				$ids[] = $term_id;
			}
		}

		if ( $ids ) {
			wp_set_post_terms( $post_id, array_values( array_unique( $ids ) ), 'post_tag' );
		}
	}

	private function get_or_create_tag( $name ) {
		$term = get_term_by( 'name', $name, 'post_tag' );
		if ( $term ) {
			return absint( $term->term_id );
		}

		if ( empty( $this->settings['create_tags'] ) ) {
			return 0;
		}

		$result = wp_insert_term( $name, 'post_tag' );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && ! empty( $data['term_id'] ) ) {
				return absint( $data['term_id'] );
			}

			return is_numeric( $data ) ? absint( $data ) : 0;
		}

		return absint( $result['term_id'] ?? 0 );
	}
}
